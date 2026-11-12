<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeGroupDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeRowDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorMergeDecision;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetAuthorDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AuthorDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorCurrentOptimizeRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Generator;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Step 2 of the merge (#87355): merges the groups of a CSV checked by people, as the plan approved them: the variants
 * go under the target authors of the main author's row (`nadriadeny_id`, empty means the main author itself). Each
 * group is guarded against the current data and merged in its own transaction; a group that no longer matches is
 * skipped with a reason, a group merged by an earlier run counts as already merged. After the commit a
 * JobAuthorCurrentOptimize of each target author moves the assets of the variants to it, and the authors of the group
 * are reindexed.
 */
final class AuthorMergeFacade
{
    use IndexManagerAwareTrait;

    private const int MAX_REPORTED_ERRORS = 50;

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly AuthorDBALRepository $authorDBALRepository,
        private readonly AssetAuthorDBALRepository $assetAuthorDBALRepository,
        private readonly AuthorCleanPhraseRepository $authorCleanPhraseRepository,
        private readonly AuthorCleanPhraseManager $authorCleanPhraseManager,
        private readonly AuthorManager $authorManager,
        private readonly JobFacade $jobFacade,
        private readonly JobAuthorCurrentOptimizeRepository $jobAuthorCurrentOptimizeRepository,
    ) {
    }

    /**
     * Checks the whole file first, so a wrong file changes nothing. Returns only the groups that merge something.
     *
     * @return list<AuthorMergeGroupDto>
     *
     * @throws InvalidArgumentException with every error found, one per line
     */
    public function readGroups(ExtSystem $extSystem, string $path): array
    {
        $errors = [];
        $rowNumbersById = [];
        $mergeRows = [];
        foreach (AuthorMergeCsv::read($path) as $rowNumber => $row) {
            $authorId = trim($row[AuthorMergeCsv::AUTHOR_ID]);
            array_push($errors, ...$this->checkRow(rowNumber: $rowNumber, row: $row, extSystem: $extSystem, seenOnRow: $rowNumbersById[$authorId] ?? null));
            $rowNumbersById[$authorId] = $rowNumber;
            $decision = AuthorMergeDecision::tryFrom(trim($row[AuthorMergeCsv::DECISION]));
            if (null !== $decision && $decision->in([AuthorMergeDecision::Main, AuthorMergeDecision::Variant])) {
                $mergeRows[trim($row[AuthorMergeCsv::GROUP])][$rowNumber] = $row;
            }
        }

        $groups = [];
        foreach ($mergeRows as $groupName => $rows) {
            $groupErrors = $this->checkGroup(groupName: (string) $groupName, rows: $rows);
            array_push($errors, ...$groupErrors);
            $group = empty($groupErrors) ? $this->createGroup(groupName: (string) $groupName, rows: $rows) : null;
            if (null === $group) {
                continue;
            }
            $groups[] = $group;
            foreach ($group->targetIds as $targetId) {
                $rowNumbersById[$targetId] ??= (int) array_key_first($rows);
            }
        }
        array_push($errors, ...$this->checkAuthors(extSystem: $extSystem, rowNumbersById: $rowNumbersById));

        if (false === empty($errors)) {
            throw $this->createInvalidCsvException($errors);
        }

        return $groups;
    }

    /**
     * Yields after each group; the generator returns the result. A dry run rolls back each group instead of the
     * commit, so it holds the locks of one group at a time, and creates no job and writes nothing to Elasticsearch.
     *
     * @param list<AuthorMergeGroupDto> $groups
     *
     * @return Generator<int, string, mixed, AuthorMergeResultDto> yields the group name
     */
    public function merge(array $groups, bool $dryRun): Generator
    {
        $result = new AuthorMergeResultDto(Uuid::v7()->toRfc4122());
        foreach ($groups as $group) {
            $this->mergeGroup(result: $result, group: $group, dryRun: $dryRun);
            $this->authorManager->clear();

            yield $group->name;
        }

        return $result;
    }

    private function mergeGroup(AuthorMergeResultDto $result, AuthorMergeGroupDto $group, bool $dryRun): void
    {
        $targets = $this->findTargets(result: $result, group: $group);
        if (null === $targets) {
            return;
        }
        $variants = $this->findVariantsToMerge(result: $result, group: $group, targets: $targets);
        if (null === $variants) {
            return;
        }
        if (empty($variants)) {
            $result->addAlreadyMerged();

            return;
        }

        $this->authorManager->beginTransaction();
        try {
            $assetLinks = $this->logPairs(runId: $result->getRunId(), groupName: $group->name, variants: $variants, targets: $targets);
            $cleanPhrases = 1 === count($targets) ? $this->replaceCleanPhraseAuthor(variants: $variants, target: $targets[App::ZERO]) : App::ZERO;
            $this->authorManager->merge(variants: $variants, targets: $targets);
            if (false === $dryRun) {
                $this->authorManager->commit();
            }
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw $exception;
        }

        if ($dryRun) {
            $this->authorManager->rollback();
            $result->addMerged(variants: count($variants), assetLinks: $assetLinks, cleanPhrases: $cleanPhrases, jobs: App::ZERO);

            return;
        }
        $jobs = $this->createJobs($targets);
        $this->indexManager->indexBulk([...$targets, ...$variants]);
        $result->addMerged(variants: count($variants), assetLinks: $assetLinks, cleanPhrases: $cleanPhrases, jobs: $jobs);
    }

    /**
     * @return non-empty-list<Author>|null null when the group is skipped
     */
    private function findTargets(AuthorMergeResultDto $result, AuthorMergeGroupDto $group): ?array
    {
        if (null === $this->findUnchanged($group->main)) {
            $result->addSkipped($group->name, sprintf('main author %s was changed or deleted', $group->main->authorId));

            return null;
        }

        $targets = [];
        foreach ($group->targetIds as $targetId) {
            $target = $this->authorRepository->find($targetId);
            if (null === $target || false === $target->getCurrentAuthors()->isEmpty() || false === $target->getFlags()->isCanBeCurrentAuthor()) {
                $result->addSkipped($group->name, sprintf('author %s cannot be a current author', $targetId));

                return null;
            }
            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * @param non-empty-list<Author> $targets
     *
     * @return list<Author>|null the variants not merged yet, null when the group is skipped
     */
    private function findVariantsToMerge(AuthorMergeResultDto $result, AuthorMergeGroupDto $group, array $targets): ?array
    {
        $targetIds = $this->toSortedIds($targets);
        $variants = [];
        foreach ($group->variants as $row) {
            $variant = $this->findUnchanged($row);
            if (null === $variant) {
                $result->addSkipped($group->name, sprintf('variant %s was changed or deleted', $row->authorId));

                return null;
            }
            if (false === $variant->getChildAuthors()->isEmpty()) {
                $result->addSkipped($group->name, sprintf('variant %s is a current author of other authors', $row->authorId));

                return null;
            }
            if ($variant->getCurrentAuthors()->isEmpty()) {
                $variants[] = $variant;

                continue;
            }
            if ($this->toSortedIds($variant->getCurrentAuthors()->toArray()) !== $targetIds) {
                $result->addSkipped($group->name, sprintf('variant %s already has other current authors', $row->authorId));

                return null;
            }
        }

        return $variants;
    }

    /**
     * One log row and the asset links of the variant per pair, as they are before the merge.
     *
     * @param list<Author> $variants
     * @param list<Author> $targets
     *
     * @return int copied asset links
     */
    private function logPairs(string $runId, string $groupName, array $variants, array $targets): int
    {
        $assetLinks = App::ZERO;
        foreach ($variants as $variant) {
            foreach ($targets as $target) {
                $this->authorDBALRepository->insertMergeLog(
                    runId: $runId,
                    groupName: $groupName,
                    variantId: (string) $variant->getId(),
                    mainAuthorId: (string) $target->getId(),
                );
                $assetLinks += $this->assetAuthorDBALRepository->copyToMergeSnapshot(
                    runId: $runId,
                    authorId: (string) $variant->getId(),
                    mainAuthorId: (string) $target->getId(),
                );
            }
        }

        return $assetLinks;
    }

    /**
     * A waiting job of the author already moves the assets of all its current children when it starts, so groups
     * under one author share it instead of each scanning all its assets.
     *
     * @param list<Author> $targets
     */
    private function createJobs(array $targets): int
    {
        $jobs = App::ZERO;
        foreach ($targets as $target) {
            $authorId = (string) $target->getId();
            if ($this->jobAuthorCurrentOptimizeRepository->existsWaitingForAuthor($authorId)) {
                continue;
            }
            $this->jobFacade->create((new JobAuthorCurrentOptimize())->setAuthorId($authorId));
            ++$jobs;
        }

        return $jobs;
    }

    /**
     * @param array<string, string> $row
     *
     * @return list<string>
     */
    private function checkRow(int $rowNumber, array $row, ExtSystem $extSystem, ?int $seenOnRow): array
    {
        $errors = [];
        if (trim($row[AuthorMergeCsv::EXT_SYSTEM]) !== $extSystem->getSlug()) {
            $errors[] = sprintf('Row %d: ext system "%s" is not "%s"', $rowNumber, $row[AuthorMergeCsv::EXT_SYSTEM], $extSystem->getSlug());
        }
        if (App::EMPTY_STRING === trim($row[AuthorMergeCsv::GROUP])) {
            $errors[] = sprintf('Row %d: empty group', $rowNumber);
        }
        if (null === AuthorMergeDecision::tryFrom(trim($row[AuthorMergeCsv::DECISION]))) {
            $errors[] = sprintf('Row %d: unknown decision "%s", allowed: %s', $rowNumber, $row[AuthorMergeCsv::DECISION], implode(', ', AuthorMergeDecision::values()));
        }
        if (null !== $seenOnRow) {
            $errors[] = sprintf('Row %d: author %s is already on row %d', $rowNumber, $row[AuthorMergeCsv::AUTHOR_ID], $seenOnRow);
        }

        return $errors;
    }

    /**
     * Merged rows of one group are one author written differently, and the group name fits the log.
     *
     * @param non-empty-array<int, array<string, string>> $rows main and variant rows by row number
     *
     * @return list<string>
     */
    private function checkGroup(string $groupName, array $rows): array
    {
        $mains = $this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Main);
        $variants = $this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Variant);
        $errors = [];
        if (count($mains) > 1) {
            $errors[] = sprintf('Group %s: %d rows "%s" (rows %s), one is allowed', $groupName, count($mains), AuthorMergeDecision::Main->value, implode(', ', array_keys($mains)));
        }
        if (empty($mains) && false === empty($variants)) {
            $errors[] = sprintf('Group %s: rows "%s" without a row "%s"', $groupName, AuthorMergeDecision::Variant->value, AuthorMergeDecision::Main->value);
        }
        if (empty($variants)) {
            return $errors;
        }
        if (mb_strlen($groupName) > AuthorDBALRepository::MERGE_LOG_GROUP_NAME_LENGTH) {
            $errors[] = sprintf('Group %s: the name is longer than %d characters', $groupName, AuthorDBALRepository::MERGE_LOG_GROUP_NAME_LENGTH);
        }
        $names = array_unique(array_map(
            static fn (array $row): string => trim($row[AuthorMergeCsv::NAME_NORMALIZED]) . ' / ' . trim($row[AuthorMergeCsv::GROUP_IDENTIFIER]),
            $rows,
        ));
        if (count($names) > 1) {
            $errors[] = sprintf('Group %s: merges different authors (%s), rows %s', $groupName, implode(', ', $names), implode(', ', array_keys($rows)));
        }
        $variantIds = array_map(static fn (array $row): string => trim($row[AuthorMergeCsv::AUTHOR_ID]), $variants);
        foreach ($mains as $main) {
            foreach (array_intersect(AuthorMergeCsv::splitList($main[AuthorMergeCsv::TARGET_IDS]), $variantIds) as $variantId) {
                $errors[] = sprintf('Group %s: variant %s cannot be a target author of its group', $groupName, $variantId);
            }
        }

        return $errors;
    }

    /**
     * @param non-empty-array<int, array<string, string>> $rows main and variant rows by row number, checked
     */
    private function createGroup(string $groupName, array $rows): ?AuthorMergeGroupDto
    {
        $toRowDto = static fn (array $row): AuthorMergeRowDto => new AuthorMergeRowDto(
            authorId: trim($row[AuthorMergeCsv::AUTHOR_ID]),
            name: $row[AuthorMergeCsv::NAME],
            identifier: $row[AuthorMergeCsv::IDENTIFIER],
        );
        $variants = array_values(array_map($toRowDto, $this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Variant)));
        if (empty($variants)) {
            return null;
        }
        $mainRow = array_values($this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Main))[App::ZERO];
        $main = $toRowDto($mainRow);

        return new AuthorMergeGroupDto(
            name: $groupName,
            main: $main,
            variants: $variants,
            targetIds: array_values(array_unique(AuthorMergeCsv::splitList($mainRow[AuthorMergeCsv::TARGET_IDS]))) ?: [$main->authorId],
        );
    }

    /**
     * @param array<int, array<string, string>> $rows by row number
     *
     * @return array<int, array<string, string>>
     */
    private function filterByDecision(array $rows, AuthorMergeDecision $decision): array
    {
        return array_filter($rows, static fn (array $row): bool => $decision->value === trim($row[AuthorMergeCsv::DECISION]));
    }

    /**
     * @param array<string, int> $rowNumbersById authors and target authors with the row that names them
     *
     * @return list<string>
     */
    private function checkAuthors(ExtSystem $extSystem, array $rowNumbersById): array
    {
        $extSystemIds = $this->authorDBALRepository->findExtSystemIds(array_map(strval(...), array_keys($rowNumbersById)));
        $errors = [];
        foreach ($rowNumbersById as $authorId => $rowNumber) {
            $extSystemId = $extSystemIds[(string) $authorId] ?? null;
            if (null === $extSystemId) {
                $errors[] = sprintf('Row %d: author "%s" does not exist', $rowNumber, $authorId);

                continue;
            }
            if ($extSystemId !== $extSystem->getId()) {
                $errors[] = sprintf('Row %d: author %s belongs to another ext system', $rowNumber, $authorId);
            }
        }

        return $errors;
    }

    /**
     * @param non-empty-list<string> $errors
     */
    private function createInvalidCsvException(array $errors): InvalidArgumentException
    {
        $more = count($errors) - self::MAX_REPORTED_ERRORS;
        $errors = array_slice($errors, App::ZERO, self::MAX_REPORTED_ERRORS);
        if ($more > App::ZERO) {
            $errors[] = sprintf('… and %d more', $more);
        }

        return new InvalidArgumentException(implode(PHP_EOL, $errors));
    }

    private function findUnchanged(AuthorMergeRowDto $row): ?Author
    {
        $author = $this->authorRepository->find($row->authorId);
        if (null === $author || $author->getName() !== $row->name || $author->getIdentifier() !== $row->identifier) {
            return null;
        }

        return $author;
    }

    /**
     * Clean phrases that put a variant on an asset put the target author there instead.
     *
     * @param list<Author> $variants
     */
    private function replaceCleanPhraseAuthor(array $variants, Author $target): int
    {
        /** @var list<AuthorCleanPhrase> $phrases */
        $phrases = $this->authorCleanPhraseRepository->findBy(['authorReplacement' => $variants]);
        foreach ($phrases as $phrase) {
            $this->authorCleanPhraseManager->updateAuthorReplacement(bannedPhrase: $phrase, authorReplacement: $target, flush: false);
        }

        return count($phrases);
    }

    /**
     * @param array<array-key, Author> $authors
     *
     * @return list<string>
     */
    private function toSortedIds(array $authors): array
    {
        $ids = array_map(static fn (Author $author): string => (string) $author->getId(), array_values($authors));
        sort($ids);

        return $ids;
    }
}
