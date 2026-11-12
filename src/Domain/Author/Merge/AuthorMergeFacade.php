<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\AuthorMergeLog;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeGroupDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeRowDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorMergeDecision;
use AnzuSystems\CoreDamBundle\Model\ValueObject\AuthorMergeResult;
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
 * Step 2 of the merge (#87355): merges the groups of a CSV checked by people. Each group is guarded against the
 * current data and merged in its own transaction; a group that no longer matches is skipped with a reason, a group
 * merged by an earlier run is skipped as done. After the commit a JobAuthorCurrentOptimize of the main author moves the
 * assets of the variants to it, and the authors of the group are reindexed.
 */
final class AuthorMergeFacade
{
    use EntityManagerAwareTrait;
    use IndexManagerAwareTrait;

    private const int MAX_REPORTED_ERRORS = 50;

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly AuthorDBALRepository $authorDBALRepository,
        private readonly AssetAuthorDBALRepository $assetAuthorDBALRepository,
        private readonly AuthorCleanPhraseRepository $authorCleanPhraseRepository,
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
        $rows = AuthorMergeCsv::read($path);
        $errors = [];
        $groups = [];
        $seenIds = [];
        foreach ($rows as $rowNumber => $row) {
            $authorId = trim($row[AuthorMergeCsv::AUTHOR_ID]);
            $groupName = trim($row[AuthorMergeCsv::GROUP]);
            $decision = AuthorMergeDecision::tryFrom(trim($row[AuthorMergeCsv::DECISION]));
            if (trim($row[AuthorMergeCsv::EXT_SYSTEM]) !== $extSystem->getSlug()) {
                $errors[] = sprintf('Row %d: ext system "%s" is not "%s"', $rowNumber, $row[AuthorMergeCsv::EXT_SYSTEM], $extSystem->getSlug());
            }
            if (App::EMPTY_STRING === $groupName) {
                $errors[] = sprintf('Row %d: empty group', $rowNumber);
            }
            if (null === $decision) {
                $errors[] = sprintf('Row %d: unknown decision "%s", allowed: %s', $rowNumber, $row[AuthorMergeCsv::DECISION], implode(', ', AuthorMergeDecision::values()));
            }
            if (isset($seenIds[$authorId])) {
                $errors[] = sprintf('Row %d: author %s is already on row %d', $rowNumber, $authorId, $seenIds[$authorId]);
            }
            $seenIds[$authorId] = $rowNumber;
            if (null === $decision || false === $decision->in([AuthorMergeDecision::Main, AuthorMergeDecision::Variant])) {
                continue;
            }

            $groups[$groupName][$decision->value][$rowNumber] = new AuthorMergeRowDto(
                authorId: $authorId,
                name: $row[AuthorMergeCsv::NAME],
                identifier: $row[AuthorMergeCsv::IDENTIFIER],
                currentAuthorCount: (int) $row[AuthorMergeCsv::CURRENT_AUTHOR_COUNT],
            );
        }

        $extSystemIds = $this->authorDBALRepository->findExtSystemIds(array_map(strval(...), array_keys($seenIds)));
        foreach ($seenIds as $authorId => $rowNumber) {
            $errors[] = $this->checkAuthorExtSystem(rowNumber: $rowNumber, authorId: (string) $authorId, extSystemId: $extSystemIds[(string) $authorId] ?? null, extSystem: $extSystem);
        }

        $mergeGroups = [];
        foreach ($groups as $groupName => $decisions) {
            $mains = $decisions[AuthorMergeDecision::Main->value] ?? [];
            $variants = array_values($decisions[AuthorMergeDecision::Variant->value] ?? []);
            $error = $this->checkGroup(groupName: (string) $groupName, mains: $mains, hasVariants: false === empty($variants));
            $errors[] = $error;
            if (null === $error && false === empty($variants)) {
                $mergeGroups[] = new AuthorMergeGroupDto(name: (string) $groupName, main: array_values($mains)[App::ZERO], variants: $variants);
            }
        }
        $errors = array_values(array_filter($errors));

        if (false === empty($errors)) {
            $more = count($errors) - self::MAX_REPORTED_ERRORS;
            $errors = array_slice($errors, App::ZERO, self::MAX_REPORTED_ERRORS);
            if ($more > App::ZERO) {
                $errors[] = sprintf('… and %d more', $more);
            }

            throw new InvalidArgumentException(implode(PHP_EOL, $errors));
        }

        return $mergeGroups;
    }

    /**
     * Yields after each group; the generator returns the result. A dry run merges everything in one transaction it
     * rolls back, and creates no job and writes nothing to Elasticsearch.
     *
     * @param list<AuthorMergeGroupDto> $groups
     *
     * @return Generator<int, string, mixed, AuthorMergeResult> yields the group name
     */
    public function merge(array $groups, bool $dryRun): Generator
    {
        $result = new AuthorMergeResult(Uuid::v7()->toRfc4122());
        if ($dryRun) {
            $this->authorManager->beginTransaction();
        }

        try {
            foreach ($groups as $group) {
                $this->mergeGroup(result: $result, group: $group, dryRun: $dryRun);
                $this->entityManager->clear();

                yield $group->name;
            }
        } finally {
            if ($dryRun) {
                $this->authorManager->rollback();
            }
        }

        return $result;
    }

    private function mergeGroup(AuthorMergeResult $result, AuthorMergeGroupDto $group, bool $dryRun): void
    {
        $main = $this->findUnchanged($group->main);
        if (null === $main) {
            $result->addSkipped($group->name, sprintf('main author %s was changed or deleted', $group->main->authorId));

            return;
        }
        if ($main->getCurrentAuthors()->count() !== $group->main->currentAuthorCount) {
            $result->addSkipped($group->name, sprintf('current authors of main author %s were changed', $group->main->authorId));

            return;
        }

        $targets = $main->getCurrentAuthors()->isEmpty() ? [$main] : array_values($main->getCurrentAuthors()->toArray());
        foreach ($targets as $target) {
            if (false === $target->getCurrentAuthors()->isEmpty() || false === $target->getFlags()->isCanBeCurrentAuthor()) {
                $result->addSkipped($group->name, sprintf('author %s cannot be a current author', (string) $target->getId()));

                return;
            }
        }

        $targetIds = $this->toSortedIds($targets);
        $pending = [];
        foreach ($group->variants as $row) {
            $variant = $this->findUnchanged($row);
            if (null === $variant) {
                $result->addSkipped($group->name, sprintf('variant %s was changed or deleted', $row->authorId));

                return;
            }
            if (false === $variant->getChildAuthors()->isEmpty()) {
                $result->addSkipped($group->name, sprintf('variant %s is a current author of other authors', $row->authorId));

                return;
            }
            if ($variant->getCurrentAuthors()->isEmpty()) {
                $pending[] = $variant;

                continue;
            }
            if ($this->toSortedIds($variant->getCurrentAuthors()->toArray()) !== $targetIds) {
                $result->addSkipped($group->name, sprintf('variant %s already has other current authors', $row->authorId));

                return;
            }
        }

        if (empty($pending)) {
            $result->addAlreadyMerged();

            return;
        }

        $this->authorManager->beginTransaction();
        try {
            $assetLinks = App::ZERO;
            foreach ($pending as $variant) {
                foreach ($targets as $target) {
                    $this->entityManager->persist(new AuthorMergeLog(
                        runId: $result->getRunId(),
                        groupName: $group->name,
                        variant: $variant,
                        mainAuthor: $target,
                        decision: AuthorMergeDecision::Variant,
                    ));
                    $assetLinks += $this->assetAuthorDBALRepository->copyToMergeSnapshot(
                        runId: $result->getRunId(),
                        authorId: (string) $variant->getId(),
                        mainAuthorId: (string) $target->getId(),
                    );
                }
            }
            $cleanPhrases = 1 === count($targets) ? $this->replaceCleanPhraseAuthor(variants: $pending, target: $targets[App::ZERO]) : App::ZERO;
            $this->authorManager->merge(variants: $pending, targets: $targets);
            $this->authorManager->commit();
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw $exception;
        }

        $jobs = $dryRun ? App::ZERO : $this->createJobs($targets);
        if (false === $dryRun) {
            $this->indexManager->indexBulk([...$targets, ...$pending]);
        }
        $result->addMerged(variants: count($pending), assetLinks: $assetLinks, cleanPhrases: $cleanPhrases, jobs: $jobs);
    }

    /**
     * A waiting job of the author already moves the assets of all its current children when it starts, so groups
     * under one author (SME is the main author of 28 groups) share it instead of each scanning all its assets.
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

    private function checkAuthorExtSystem(int $rowNumber, string $authorId, ?int $extSystemId, ExtSystem $extSystem): ?string
    {
        if (null === $extSystemId) {
            return sprintf('Row %d: author "%s" does not exist', $rowNumber, $authorId);
        }
        if ($extSystemId !== $extSystem->getId()) {
            return sprintf('Row %d: author %s belongs to another ext system', $rowNumber, $authorId);
        }

        return null;
    }

    /**
     * @param array<int, AuthorMergeRowDto> $mains by row number
     */
    private function checkGroup(string $groupName, array $mains, bool $hasVariants): ?string
    {
        if (count($mains) > 1) {
            return sprintf('Group %s: %d rows "%s" (rows %s), one is allowed', $groupName, count($mains), AuthorMergeDecision::Main->value, implode(', ', array_keys($mains)));
        }
        if (empty($mains) && $hasVariants) {
            return sprintf('Group %s: rows "%s" without a row "%s"', $groupName, AuthorMergeDecision::Variant->value, AuthorMergeDecision::Main->value);
        }

        return null;
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
     * Clean phrases that put the variant on an asset put the main author there instead.
     *
     * @param list<Author> $variants
     */
    private function replaceCleanPhraseAuthor(array $variants, Author $target): int
    {
        /** @var list<AuthorCleanPhrase> $phrases */
        $phrases = $this->authorCleanPhraseRepository->findBy(['authorReplacement' => $variants]);
        foreach ($phrases as $phrase) {
            $phrase->setAuthorReplacement($target);
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
