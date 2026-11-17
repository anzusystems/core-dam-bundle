<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizer;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;
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
 * Step 2 of the merge (#87355): merges the groups of a CSV checked by people, only those approved (`approved` 1),
 * as their decisions say: the variants go under the target authors of the main author's row, and so does the main
 * author when it is not a target (a credit like "(TASR)" put under TASR, also as a group of one row). The targets are
 * the authors of `target_ids` and the names of `new_sources` and `new_authors`, an author with exactly that name and no
 * identifier, created when there is none; with neither, the main author itself. Child authors of a merged author go
 * under its targets too. A main author alone in its group that is its own target stays in place, and its assets get
 * the other targets directly (a web that stays the author of its photos and gets the source Reprofoto). Each group
 * is guarded against the current data and merged in its own transaction; a group that no longer matches is skipped
 * with a reason, a group merged by an earlier run counts as already merged. Each merged author and child author, and
 * a main author that stays, is logged with its asset links before the merge. After the commit a
 * JobAuthorCurrentOptimize of each target author moves the assets of the variants to it, or a JobAuthorNameReindex
 * reindexes the assets of a main author that stays, and the authors of the group are reindexed.
 */
final class AuthorMergeFacade
{
    use IndexManagerAwareTrait;

    private const int MAX_REPORTED_ERRORS = 50;
    private const array TARGET_COLUMNS = [AuthorMergeCsv::TARGET_IDS, AuthorMergeCsv::NEW_SOURCES, AuthorMergeCsv::NEW_AUTHORS];

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
        $mergeRowNumbersById = [];
        $approvedRowNumbers = [];
        $mergeRows = [];
        foreach (AuthorMergeCsv::read($path) as $rowNumber => $row) {
            $authorId = trim($row[AuthorMergeCsv::AUTHOR_ID]);
            $decision = AuthorMergeDecision::tryFrom(trim($row[AuthorMergeCsv::DECISION]));
            $isMergeRow = null !== $decision && $decision->in([AuthorMergeDecision::Main, AuthorMergeDecision::Variant]);
            array_push($errors, ...$this->checkRow(rowNumber: $rowNumber, row: $row, extSystem: $extSystem, seenOnRow: $isMergeRow ? $mergeRowNumbersById[$authorId] ?? null : null));
            $rowNumbersById[$authorId] ??= $rowNumber;
            if ($isMergeRow) {
                $mergeRowNumbersById[$authorId] = $rowNumber;
            }
            $groupName = trim($row[AuthorMergeCsv::GROUP]);
            $approved = trim($row[AuthorMergeCsv::APPROVED]);
            $approvedRowNumbers[$groupName][$approved] ??= $rowNumber;
            if (AuthorMergeCsv::APPROVED_YES === $approved && $isMergeRow) {
                $mergeRows[$groupName][$rowNumber] = $row;
            }
        }
        foreach ($approvedRowNumbers as $groupName => $rowNumbers) {
            if (count($rowNumbers) > 1) {
                $errors[] = sprintf('Group %s: "%s" differs between rows %s; set it on all rows of the group', $groupName, AuthorMergeCsv::APPROVED, implode(', ', $rowNumbers));
            }
        }

        $groups = [];
        $groupsByMainRow = [];
        foreach ($mergeRows as $groupName => $rows) {
            $groupErrors = $this->checkGroup(groupName: (string) $groupName, rows: $rows);
            array_push($errors, ...$groupErrors);
            $group = empty($groupErrors) ? $this->createGroup(groupName: (string) $groupName, rows: $rows) : null;
            if (null === $group) {
                continue;
            }
            $groups[] = $group;
            $groupsByMainRow[(int) array_key_first($this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Main))] = $group;
            foreach ($group->targetIds as $targetId) {
                $rowNumbersById[$targetId] ??= (int) array_key_first($rows);
            }
        }
        array_push($errors, ...$this->checkTargetsAreNotVariants($groups));
        array_push($errors, ...$this->checkNamedTargets($groupsByMainRow));
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
        $main = $this->findUnchanged($group->main);
        if (null === $main) {
            $result->addSkipped($group->name, sprintf('main author %s was changed or deleted', $group->main->authorId));

            return;
        }
        $targets = $this->findTargets(result: $result, group: $group, extSystem: $main->getExtSystem());
        if (null === $targets) {
            return;
        }
        $variants = $this->findVariantsToMerge(result: $result, group: $group, targets: $targets);
        if (null === $variants) {
            return;
        }
        $mainStays = empty($group->variants) && in_array($group->main->authorId, $this->toSortedIds($targets['authors']), true);
        if (empty($variants) && false === $mainStays) {
            $result->addAlreadyMerged();

            return;
        }
        $children = array_merge(...array_map(static fn (Author $variant): array => $variant->getChildAuthors()->getValues(), $variants));

        $this->authorManager->beginTransaction();

        try {
            $created = $this->createTargets(extSystem: $main->getExtSystem(), sourceByName: $targets['missingSourceByName']);
            $allTargets = [...$targets['authors'], ...$created];
            $createdIds = $this->toSortedIds($created);
            $linkTargets = $mainStays ? array_values(array_filter($allTargets, static fn (Author $target): bool => (string) $target->getId() !== $group->main->authorId)) : [];
            $assetLinks = $mainStays
                ? $this->logPairs(runId: $result->getRunId(), groupName: $group->name, variants: [$main], targets: $linkTargets, createdIds: $createdIds)
                : $this->logPairs(runId: $result->getRunId(), groupName: $group->name, variants: [...$variants, ...$children], targets: $allTargets, createdIds: $createdIds);
            $cleanPhrases = 1 === count($allTargets) ? $this->replaceCleanPhraseAuthor(variants: [...$variants, ...$children], target: $allTargets[App::ZERO]) : App::ZERO;
            $this->authorManager->merge(variants: $variants, targets: $allTargets);
            $linkedAssets = $this->linkAssets(author: $main, targets: $linkTargets);
            if ($mainStays && empty($created) && App::ZERO === $linkedAssets) {
                $this->authorManager->rollback();
                $result->addAlreadyMerged();

                return;
            }
            if (false === $dryRun) {
                $this->authorManager->commit();
            }
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw $exception;
        }

        if ($dryRun) {
            $this->authorManager->rollback();
            $result->addMerged(variants: count($variants), childAuthors: count($children), createdAuthors: count($created), assetLinks: $assetLinks, cleanPhrases: $cleanPhrases, jobs: App::ZERO);

            return;
        }
        $jobs = $mainStays ? $this->createAssetReindexJob($main) : $this->createJobs($allTargets);
        $this->indexManager->indexBulk([...$allTargets, ...$variants, ...$children]);
        $result->addMerged(variants: count($variants), childAuthors: count($children), createdAuthors: count($created), assetLinks: $assetLinks, cleanPhrases: $cleanPhrases, jobs: $jobs);
    }

    /**
     * A named target is the author with exactly that name and no identifier, or the current authors it was merged
     * under. A name that differs only in case or accents is not the same author, but the unique key forbids creating
     * it next to the existing one, so the group is skipped.
     *
     * @return array{authors: list<Author>, missingSourceByName: array<string, bool>}|null null when the group is skipped
     */
    private function findTargets(AuthorMergeResultDto $result, AuthorMergeGroupDto $group, ExtSystem $extSystem): ?array
    {
        $targets = [];
        foreach ($group->targetIds as $targetId) {
            $target = $this->authorRepository->find($targetId);
            if (null === $target || false === $this->canBeTarget($target)) {
                $result->addSkipped($group->name, sprintf('author %s cannot be a current author', $targetId));

                return null;
            }
            $targets[$targetId] = $target;
        }

        $missingSourceByName = [];
        $namedTargets = [
            ...array_map(static fn (string $name): array => [$name, true], $group->newSourceNames),
            ...array_map(static fn (string $name): array => [$name, false], $group->newAuthorNames),
        ];
        foreach ($namedTargets as [$name, $source]) {
            $matches = $this->authorRepository->findByNameAndExtSystem($name, $extSystem, App::EMPTY_STRING);
            $match = array_find($matches, static fn (Author $author): bool => $author->getName() === $name);
            if (null === $match && false === empty($matches)) {
                $result->addSkipped($group->name, sprintf('target "%s" exists as "%s"', $name, $matches[App::ZERO]->getName()));

                return null;
            }
            if (null === $match) {
                $missingSourceByName[$name] = $source;

                continue;
            }
            foreach ($match->getCurrentAuthors()->isEmpty() ? [$match] : $match->getCurrentAuthors()->getValues() as $target) {
                if (false === $this->canBeTarget($target)) {
                    $result->addSkipped($group->name, sprintf('author %s cannot be a current author', (string) $target->getId()));

                    return null;
                }
                if ($target->getFlags()->isSource() !== $source) {
                    $result->addSkipped($group->name, sprintf('target "%s" %s a source', $target->getName(), $source ? 'is not' : 'is'));

                    return null;
                }
                $targets[(string) $target->getId()] = $target;
            }
        }

        return ['authors' => array_values($targets), 'missingSourceByName' => $missingSourceByName];
    }

    private function canBeTarget(Author $target): bool
    {
        return $target->getCurrentAuthors()->isEmpty() && $target->getFlags()->isCanBeCurrentAuthor();
    }

    /**
     * A main author merged elsewhere since the plan keeps its current authors while its variants still go under the
     * approved targets; alone in its group it is skipped, so it is not counted as merged.
     *
     * @param array{authors: list<Author>, missingSourceByName: array<string, bool>} $targets
     *
     * @return list<Author>|null the variants not merged yet, null when the group is skipped
     */
    private function findVariantsToMerge(AuthorMergeResultDto $result, AuthorMergeGroupDto $group, array $targets): ?array
    {
        $targetIds = $this->toSortedIds($targets['authors']);
        $mainIsTarget = in_array($group->main->authorId, $targetIds, true);
        $variants = [];
        foreach ($mainIsTarget ? $group->variants : [$group->main, ...$group->variants] as $row) {
            $variant = $this->findUnchanged($row);
            if (null === $variant) {
                $result->addSkipped($group->name, sprintf('author %s was changed or deleted', $row->authorId));

                return null;
            }
            if ($variant->getCurrentAuthors()->isEmpty()) {
                $variants[] = $variant;

                continue;
            }
            $isMerged = empty($targets['missingSourceByName']) && $this->toSortedIds($variant->getCurrentAuthors()->toArray()) === $targetIds;
            if ($isMerged || ($row === $group->main && false === empty($group->variants))) {
                continue;
            }
            $result->addSkipped($group->name, sprintf('author %s already has other current authors', $row->authorId));

            return null;
        }

        return $variants;
    }

    /**
     * @param array<string, bool> $sourceByName
     *
     * @return list<Author>
     */
    private function createTargets(ExtSystem $extSystem, array $sourceByName): array
    {
        $created = [];
        foreach ($sourceByName as $name => $source) {
            $author = (new Author())
                ->setExtSystem($extSystem)
                ->setName((string) $name);
            $author->getFlags()->setSource($source);
            $created[] = $this->authorManager->create($author);
        }

        return $created;
    }

    /**
     * One log row and the asset links of the variant per pair, as they are before the merge.
     *
     * @param list<Author> $variants
     * @param list<Author> $targets
     * @param list<string> $createdIds targets the merge created
     *
     * @return int copied asset links
     */
    private function logPairs(string $runId, string $groupName, array $variants, array $targets, array $createdIds): int
    {
        $assetLinks = App::ZERO;
        foreach ($variants as $variant) {
            foreach ($targets as $target) {
                $this->authorDBALRepository->insertMergeLog(
                    runId: $runId,
                    groupName: $groupName,
                    variantId: (string) $variant->getId(),
                    mainAuthorId: (string) $target->getId(),
                    mainAuthorCreated: in_array((string) $target->getId(), $createdIds, true),
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
     * @param list<Author> $targets
     *
     * @return int asset links added
     */
    private function linkAssets(Author $author, array $targets): int
    {
        $linked = App::ZERO;
        foreach ($targets as $target) {
            $linked += $this->assetAuthorDBALRepository->linkAssetsOfAuthor(
                authorId: (string) $author->getId(),
                targetId: (string) $target->getId(),
                source: $target->getFlags()->isSource(),
            );
        }

        return $linked;
    }

    private function createAssetReindexJob(Author $author): int
    {
        $this->jobFacade->create((new JobAuthorNameReindex())->setAuthorId((string) $author->getId()));

        return 1;
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
        if (false === in_array(trim($row[AuthorMergeCsv::APPROVED]), [AuthorMergeCsv::APPROVED_YES, AuthorMergeCsv::APPROVED_NO], true)) {
            $errors[] = sprintf('Row %d: "%s" is "%s", allowed: %s, %s', $rowNumber, AuthorMergeCsv::APPROVED, $row[AuthorMergeCsv::APPROVED], AuthorMergeCsv::APPROVED_YES, AuthorMergeCsv::APPROVED_NO);
        }
        if (null === AuthorMergeDecision::tryFrom(trim($row[AuthorMergeCsv::DECISION]))) {
            $errors[] = sprintf('Row %d: unknown decision "%s", allowed: %s', $rowNumber, $row[AuthorMergeCsv::DECISION], implode(', ', AuthorMergeDecision::values()));
        }
        if (null !== $seenOnRow) {
            $errors[] = sprintf('Row %d: author %s is already merged on row %d', $rowNumber, $row[AuthorMergeCsv::AUTHOR_ID], $seenOnRow);
        }

        return $errors;
    }

    /**
     * Merged rows of one group are one author written differently, the group name fits the log, and the targets are
     * set on the main row only.
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
        $mainRowNumber = array_key_first($mains);
        if (null === $mainRowNumber) {
            return $errors;
        }
        $mainRow = $mains[$mainRowNumber];
        foreach ($variants as $rowNumber => $variant) {
            foreach (self::TARGET_COLUMNS as $column) {
                $values = $this->findSortedList(row: $variant, column: $column);
                $mainValues = AuthorMergeCsv::TARGET_IDS === $column ? $this->findMainTargetIds($mainRow) : $this->findSortedList(row: $mainRow, column: $column);
                if (false === empty($values) && $values !== $mainValues) {
                    $errors[] = sprintf('Row %d: "%s" differs from the main row %d; copy it from row %d or leave it empty', $rowNumber, $column, $mainRowNumber, $mainRowNumber);
                }
            }
        }
        $sourceNames = $this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_SOURCES);
        $authorNames = $this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_AUTHORS);
        foreach (array_intersect($sourceNames, $authorNames) as $name) {
            $errors[] = sprintf('Row %d: "%s" is in both "%s" and "%s"', $mainRowNumber, $name, AuthorMergeCsv::NEW_SOURCES, AuthorMergeCsv::NEW_AUTHORS);
        }
        foreach ([...$sourceNames, ...$authorNames] as $name) {
            if (mb_strlen($name) > Author::NAME_MAX_LENGTH) {
                $errors[] = sprintf('Row %d: the name "%s" is longer than %d characters', $mainRowNumber, $name, Author::NAME_MAX_LENGTH);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $mainRow
     *
     * @return list<string> sorted, the main author itself when the row names no target
     */
    private function findMainTargetIds(array $mainRow): array
    {
        $targetIds = $this->findSortedList(row: $mainRow, column: AuthorMergeCsv::TARGET_IDS);
        $hasNamedTargets = false === empty($this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_SOURCES))
            || false === empty($this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_AUTHORS));

        return $targetIds ?: ($hasNamedTargets ? [] : [trim($mainRow[AuthorMergeCsv::AUTHOR_ID])]);
    }

    /**
     * @param array<string, string> $row
     *
     * @return list<string> sorted
     */
    private function findSortedList(array $row, string $column): array
    {
        $values = array_values(array_unique(AuthorMergeCsv::splitList($row[$column])));
        sort($values);

        return $values;
    }

    /**
     * A variant merged by one group cannot be a current author, so a group with it as a target would be skipped; so can
     * a main author that is not its own target.
     *
     * @param list<AuthorMergeGroupDto> $groups
     *
     * @return list<string>
     */
    private function checkTargetsAreNotVariants(array $groups): array
    {
        $variantGroups = [];
        foreach ($groups as $group) {
            $mainMayMove = false === in_array($group->main->authorId, $group->targetIds, true);
            foreach ([...$group->variants, ...($mainMayMove ? [$group->main] : [])] as $variant) {
                $variantGroups[$variant->authorId] = $group->name;
            }
        }
        $errors = [];
        foreach ($groups as $group) {
            foreach ($group->targetIds as $targetId) {
                if (array_key_exists($targetId, $variantGroups)) {
                    $errors[] = sprintf('Group %s: target author %s is a variant of group %s', $group->name, $targetId, $variantGroups[$targetId]);
                }
            }
        }

        return $errors;
    }

    /**
     * The first group that names a new target creates it and the later groups find it, so a later group that writes it
     * otherwise or names it as the other kind would be skipped, and a dry run, which rolls back each group, cannot see it.
     *
     * @param array<int, AuthorMergeGroupDto> $groupsByMainRow
     *
     * @return list<string>
     */
    private function checkNamedTargets(array $groupsByMainRow): array
    {
        ksort($groupsByMainRow);
        $firstByKey = [];
        $errors = [];
        foreach ($groupsByMainRow as $rowNumber => $group) {
            foreach ([AuthorMergeCsv::NEW_SOURCES => $group->newSourceNames, AuthorMergeCsv::NEW_AUTHORS => $group->newAuthorNames] as $column => $names) {
                foreach ($names as $name) {
                    $first = $firstByKey[AuthorNameNormalizer::normalize($name)] ??= ['name' => $name, 'column' => $column, 'row' => $rowNumber];
                    if ($first['name'] !== $name || $first['column'] !== $column) {
                        $errors[] = sprintf('Row %d: "%s" in "%s" is "%s" in "%s" on row %d; write it the same', $rowNumber, $name, $column, $first['name'], $first['column'], $first['row']);
                    }
                }
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
        $mainRow = array_values($this->filterByDecision(rows: $rows, decision: AuthorMergeDecision::Main))[App::ZERO];
        $main = $toRowDto($mainRow);
        $targetIds = $this->findMainTargetIds($mainRow);
        if (empty($variants) && [$main->authorId] === $targetIds) {
            return null;
        }

        return new AuthorMergeGroupDto(
            name: $groupName,
            main: $main,
            variants: $variants,
            targetIds: $targetIds,
            newSourceNames: $this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_SOURCES),
            newAuthorNames: $this->findSortedList(row: $mainRow, column: AuthorMergeCsv::NEW_AUTHORS),
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
