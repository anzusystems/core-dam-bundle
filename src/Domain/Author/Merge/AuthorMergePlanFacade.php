<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizer;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeCandidateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeProposalDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorMergeDecision;
use AnzuSystems\CoreDamBundle\Model\ValueObject\AuthorMergePlanResult;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AuthorDBALRepository;

/**
 * Step 1 of the merge: proposes groups of duplicate authors for people to check (#87355).
 * A group is the same normalized name and the same identifier, so namesakes with different identifiers are never
 * merged. The main author is the one with the most assets (then reviewed, then older); a member that is already a
 * current author, or that already has current authors set by a person, wins and the others follow it. Groups the
 * rules cannot decide are not approved: people check the prefilled decisions and approve them, or decide groups
 * prefilled with `do not merge` themselves.
 */
final readonly class AuthorMergePlanFacade
{
    private const array MEMBER_ORDER = [
        AuthorMergeDecision::Main->value => 0,
        AuthorMergeDecision::Variant->value => 1,
        AuthorMergeDecision::DoNotMerge->value => 1,
        AuthorMergeDecision::NoChange->value => 2,
    ];
    private const string REASON_MOST_ASSETS = 'most assets';
    private const string REASON_IS_CURRENT_AUTHOR = 'already a current author of other authors';
    private const string REASON_HAS_CURRENT_AUTHORS = 'already has current authors, the others get the same';
    private const string MANUAL_TWO_CURRENT_AUTHORS = 'two or more current authors in the group';
    private const string MANUAL_OTHER_CURRENT_AUTHOR = 'a member has another current author than the one in the group';
    private const string MANUAL_DIFFERENT_CURRENT_AUTHORS = 'members have different current authors';
    private const string MANUAL_INITIALS = 'initials differ only in diacritics, they can be two people';
    private const string MANUAL_DIACRITICS = 'names differ in diacritics, two people or only a misspelling';
    private const string MANUAL_UNCOVERED_NAME = 'current authors of the main do not cover its whole name, variants would lose a part';
    private const array MANUAL_CASES = [
        self::MANUAL_INITIALS => 'M1 (diacritics)',
        self::MANUAL_DIFFERENT_CURRENT_AUTHORS => 'M2 (different current authors)',
        self::MANUAL_OTHER_CURRENT_AUTHOR => 'M2 (different current authors)',
        self::MANUAL_TWO_CURRENT_AUTHORS => 'M3 (two current authors)',
        self::MANUAL_UNCOVERED_NAME => 'M4 (name not covered)',
        self::MANUAL_DIACRITICS => 'M5 (diacritics in name)',
    ];

    /**
     * The rules propose no main author here: merging under the main's current authors would drop a part of a name
     * (only a source or a person may be added, never lost), and two current authors are two people.
     */
    private const array MANUAL_WITHOUT_PROPOSAL = [self::MANUAL_TWO_CURRENT_AUTHORS, self::MANUAL_UNCOVERED_NAME];

    /**
     * Normalized words of a credit that may be lost, the only ones (owner 2026-11-12): "ilustračné foto", "ilustr",
     * "zdroj:", "koláž", "vizualizácia", "screenshot". A source (SME, Archív SME, Reprofoto, MY …) or a person never is.
     */
    private const array NOISE_WORDS = ['foto', 'ilustracne', 'ilustr', 'zdroj', 'kolaz', 'vizualizacia', 'screenshot'];
    private const string SINGLE_CASE = 'S (single author)';
    private const string SINGLE_REASON = 'no duplicate; listed to set its current authors by hand';
    private const int SINGLE_MIN_ASSETS = 10;
    private const string YES = 'yes';
    private const string NO = 'no';

    public function __construct(
        private AuthorDBALRepository $authorDBALRepository,
    ) {
    }

    public function writePlan(ExtSystem $extSystem, string $path): AuthorMergePlanResult
    {
        $proposals = $this->createProposals($this->authorDBALRepository->findMergeCandidates(extSystemId: (int) $extSystem->getId(), minAssets: self::SINGLE_MIN_ASSETS));
        $rows = [];
        $variants = App::ZERO;
        foreach ($proposals as $index => $proposal) {
            foreach ($proposal->rows as $row) {
                if (AuthorMergeDecision::Variant->value === $row[AuthorMergeCsv::DECISION] && AuthorMergeCsv::APPROVED_YES === $row[AuthorMergeCsv::APPROVED]) {
                    ++$variants;
                }
                $row[AuthorMergeCsv::GROUP] = $index + 1;
                $row[AuthorMergeCsv::CASE] = $proposal->case;
                $row[AuthorMergeCsv::EXT_SYSTEM] = $extSystem->getSlug();
                $rows[] = array_map(static fn (string $column): string|int => $row[$column], AuthorMergeCsv::COLUMNS);
            }
        }
        AuthorMergeCsv::write($path, $rows);

        $singleAuthors = count(array_filter($proposals, static fn (AuthorMergeProposalDto $proposal): bool => self::SINGLE_CASE === $proposal->case));

        return new AuthorMergePlanResult(
            groups: count($proposals) - $singleAuthors,
            manualGroups: count(array_filter($proposals, static fn (AuthorMergeProposalDto $proposal): bool => $proposal->manual)),
            authors: count($rows),
            variants: $variants,
            singleAuthors: $singleAuthors,
        );
    }

    /**
     * Groups to approve first, then by case, then by assets of the group, largest first. Authors without a duplicate
     * follow at the end when they are used (assets or child authors), so a person can still give them current authors.
     *
     * @param list<AuthorMergeCandidateDto> $candidates
     *
     * @return list<AuthorMergeProposalDto>
     */
    private function createProposals(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate->nameNormalized . "\0" . $this->findGroupIdentifier($candidate)][] = $candidate;
        }
        $currentAuthorNames = $this->findCurrentAuthorNames($candidates);

        $proposals = [];
        $singles = [];
        foreach ($groups as $key => $group) {
            if (1 === count($group)) {
                if ($group[App::ZERO]->assetCount >= self::SINGLE_MIN_ASSETS || $group[App::ZERO]->childAuthorCount > App::ZERO) {
                    $singles[] = $this->proposeSingle(key: (string) $key, candidate: $group[App::ZERO], currentAuthorNames: $currentAuthorNames);
                }

                continue;
            }
            $manualReason = $this->findManualReason(group: $group, currentAuthorNames: $currentAuthorNames);
            $proposals[] = in_array($manualReason, self::MANUAL_WITHOUT_PROPOSAL, true)
                ? $this->proposeManualGroup(key: (string) $key, group: $group, reason: (string) $manualReason, currentAuthorNames: $currentAuthorNames)
                : $this->proposeGroup(key: (string) $key, group: $group, currentAuthorNames: $currentAuthorNames, manualReason: $manualReason);
        }
        usort(
            $proposals,
            static fn (AuthorMergeProposalDto $first, AuthorMergeProposalDto $second): int => [$second->manual, $first->case, $second->assetCount, $first->key] <=> [$first->manual, $second->case, $first->assetCount, $second->key],
        );
        usort(
            $singles,
            static fn (AuthorMergeProposalDto $first, AuthorMergeProposalDto $second): int => [$second->assetCount, $first->key] <=> [$first->assetCount, $second->key],
        );

        return [...$proposals, ...$singles];
    }

    /**
     * @param array<string, string> $currentAuthorNames
     */
    private function proposeSingle(string $key, AuthorMergeCandidateDto $candidate, array $currentAuthorNames): AuthorMergeProposalDto
    {
        return new AuthorMergeProposalDto(
            manual: false,
            case: self::SINGLE_CASE,
            assetCount: $candidate->assetCount,
            key: $key,
            rows: [$this->createRow(
                group: [$candidate],
                candidate: $candidate,
                approved: true,
                decision: AuthorMergeDecision::NoChange,
                targetIds: App::EMPTY_STRING,
                targetNames: App::EMPTY_STRING,
                reason: self::SINGLE_REASON,
                currentAuthorNames: $currentAuthorNames,
            )],
        );
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, string> $currentAuthorNames
     */
    private function findManualReason(array $group, array $currentAuthorNames): ?string
    {
        $holders = $this->findCurrentAuthorsOfOthers($group);
        if (count($holders) > 1) {
            return self::MANUAL_TWO_CURRENT_AUTHORS;
        }

        $currentAuthorSets = [];
        foreach ($group as $candidate) {
            $ids = $candidate->currentAuthorIds;
            sort($ids);
            $currentAuthorSets[implode(',', $ids)] = true;
        }
        unset($currentAuthorSets[App::EMPTY_STRING]);

        if (false === empty($holders) && false === empty($currentAuthorSets) && array_keys($currentAuthorSets) !== [$holders[App::ZERO]->id]) {
            return self::MANUAL_OTHER_CURRENT_AUTHOR;
        }
        if (empty($holders) && count($currentAuthorSets) > 1) {
            return self::MANUAL_DIFFERENT_CURRENT_AUTHORS;
        }
        if (empty($holders) && 1 === count($currentAuthorSets) && count($this->findMapped($group)) < count($group) && false === empty($this->findUncoveredWords(group: $group, currentAuthorNames: $currentAuthorNames))) {
            return self::MANUAL_UNCOVERED_NAME;
        }

        if (false === $this->differsInLetters($group)) {
            return null;
        }

        return $this->hasInitial($group) ? self::MANUAL_INITIALS : self::MANUAL_DIACRITICS;
    }

    /**
     * Members that differ in letters, not only in spaces, punctuation and case, are left to people: they share the
     * normalized name, so the letters differ only in diacritics or in letters like "ł". "Š. Hraško" and "S.Hraško",
     * "Martin Krupa" and "Martin Krúpa", "Hospodárske noviny" and "Hospodářské noviny" can be two, and a main
     * without diacritics would replace the right spelling on the assets of the variants.
     *
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function differsInLetters(array $group): bool
    {
        $spellings = array_unique(array_map(
            static fn (AuthorMergeCandidateDto $candidate): string => (string) preg_replace('/\W/u', App::EMPTY_STRING, mb_strtolower($candidate->name)),
            $group,
        ));

        return count($spellings) > 1;
    }

    /**
     * An initial is a single letter with a dot.
     *
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function hasInitial(array $group): bool
    {
        return (bool) preg_match('/\b\w\s*\./u', $group[App::ZERO]->nameNormalized);
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, string> $currentAuthorNames
     */
    private function proposeGroup(string $key, array $group, array $currentAuthorNames, ?string $manualReason): AuthorMergeProposalDto
    {
        $holders = $this->findCurrentAuthorsOfOthers($group);
        $mapped = $this->findMapped($group);
        $main = match (true) {
            false === empty($holders) => $holders[App::ZERO],
            false === empty($mapped) => $this->findMostUsed($mapped),
            default => $this->findMostUsed($group),
        };
        $reason = $manualReason ?? match (true) {
            false === empty($holders) => self::REASON_IS_CURRENT_AUTHOR,
            false === empty($mapped) => self::REASON_HAS_CURRENT_AUTHORS,
            default => self::REASON_MOST_ASSETS,
        };
        $targetIds = $main->currentAuthorIds ?: [$main->id];
        $targetNames = array_map(static fn (string $id): string => $currentAuthorNames[$id] ?? $id, $targetIds);

        $decisions = [];
        foreach ($group as $candidate) {
            $decisions[$candidate->id] = match (true) {
                $candidate->id === $main->id => AuthorMergeDecision::Main,
                false === empty($candidate->currentAuthorIds) => AuthorMergeDecision::NoChange,
                default => AuthorMergeDecision::Variant,
            };
        }

        $rows = [];
        foreach ($this->sortMembers(group: $group, decisions: $decisions) as $candidate) {
            $rows[] = $this->createRow(
                group: $group,
                candidate: $candidate,
                approved: null === $manualReason,
                decision: $decisions[$candidate->id],
                targetIds: AuthorMergeCsv::joinList($targetIds),
                targetNames: AuthorMergeCsv::joinList($targetNames),
                reason: $reason,
                currentAuthorNames: $currentAuthorNames,
            );
        }

        return new AuthorMergeProposalDto(
            manual: null !== $manualReason,
            case: null === $manualReason
                ? $this->describeGroup(group: $group, main: $main, hasVariants: in_array(AuthorMergeDecision::Variant, $decisions, true))
                : self::MANUAL_CASES[$manualReason],
            assetCount: $this->sumAssets($group),
            key: $key,
            rows: $rows,
        );
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, string> $currentAuthorNames
     */
    private function proposeManualGroup(string $key, array $group, string $reason, array $currentAuthorNames): AuthorMergeProposalDto
    {
        $decisions = array_fill_keys(array_map(static fn (AuthorMergeCandidateDto $candidate): string => $candidate->id, $group), AuthorMergeDecision::DoNotMerge);
        $uncoveredWords = self::MANUAL_UNCOVERED_NAME === $reason ? $this->findUncoveredWords(group: $group, currentAuthorNames: $currentAuthorNames) : [];

        return new AuthorMergeProposalDto(
            manual: true,
            case: self::MANUAL_CASES[$reason],
            assetCount: $this->sumAssets($group),
            key: $key,
            rows: array_map(
                fn (AuthorMergeCandidateDto $candidate): array => $this->createRow(
                    group: $group,
                    candidate: $candidate,
                    approved: false,
                    decision: AuthorMergeDecision::DoNotMerge,
                    targetIds: App::EMPTY_STRING,
                    targetNames: App::EMPTY_STRING,
                    reason: empty($uncoveredWords) ? $reason : sprintf('%s (%s)', $reason, implode(', ', $uncoveredWords)),
                    currentAuthorNames: $currentAuthorNames,
                ),
                $this->sortMembers(group: $group, decisions: $decisions),
            ),
        );
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function describeGroup(array $group, AuthorMergeCandidateDto $main, bool $hasVariants): string
    {
        $reviewedCount = $this->countReviewed($group);

        return match (true) {
            false === $hasVariants => 'A7 (already clean)',
            $main->childAuthorCount > App::ZERO => 'A5 (main has children)',
            false === empty($main->currentAuthorIds) => 'A6 (set by a person)',
            App::ZERO === $reviewedCount => 'A1 (nobody reviewed)',
            1 === $reviewedCount && $main->reviewed => 'A2 (main reviewed)',
            1 === $reviewedCount => 'A3 (variant reviewed)',
            default => 'A4 (several reviewed)',
        };
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     *
     * @return list<AuthorMergeCandidateDto>
     */
    private function findCurrentAuthorsOfOthers(array $group): array
    {
        return array_values(array_filter($group, static fn (AuthorMergeCandidateDto $candidate): bool => $candidate->childAuthorCount > App::ZERO));
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     *
     * @return list<AuthorMergeCandidateDto>
     */
    private function findMapped(array $group): array
    {
        return array_values(array_filter($group, static fn (AuthorMergeCandidateDto $candidate): bool => false === empty($candidate->currentAuthorIds)));
    }

    /**
     * The variants get the current authors of the main author (the mapped member with the most assets), so a word of
     * the name they do not carry would vanish from the assets: "Korzár/Michal Frank" under "Michal Frank" loses Korzár.
     * Noise words do not count.
     *
     * @param list<AuthorMergeCandidateDto> $group with a mapped member
     * @param array<string, string> $currentAuthorNames
     *
     * @return list<string> normalized words of the main author's name
     */
    private function findUncoveredWords(array $group, array $currentAuthorNames): array
    {
        $main = $this->findMostUsed($this->findMapped($group));
        $covered = [];
        foreach ($main->currentAuthorIds as $id) {
            array_push($covered, ...$this->splitWords($currentAuthorNames[$id] ?? App::EMPTY_STRING));
        }

        return array_values(array_unique(array_diff($this->splitWords($main->name), $covered, self::NOISE_WORDS)));
    }

    /**
     * @return list<string>
     */
    private function splitWords(string $name): array
    {
        return (array) preg_split('/[^\p{L}\p{N}]+/u', AuthorNameNormalizer::normalize($name), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param non-empty-list<AuthorMergeCandidateDto> $candidates
     */
    private function findMostUsed(array $candidates): AuthorMergeCandidateDto
    {
        usort(
            $candidates,
            static fn (AuthorMergeCandidateDto $first, AuthorMergeCandidateDto $second): int => [$second->assetCount, $second->reviewed, $first->createdAt, $first->id] <=> [$first->assetCount, $first->reviewed, $second->createdAt, $second->id],
        );

        return $candidates[App::ZERO];
    }

    /**
     * Main author first, then the variants, then the members that stay; by assets inside.
     *
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, AuthorMergeDecision> $decisions
     *
     * @return list<AuthorMergeCandidateDto>
     */
    private function sortMembers(array $group, array $decisions): array
    {
        usort(
            $group,
            static fn (AuthorMergeCandidateDto $first, AuthorMergeCandidateDto $second): int => [self::MEMBER_ORDER[$decisions[$first->id]->value], $second->assetCount, $first->name] <=> [self::MEMBER_ORDER[$decisions[$second->id]->value], $first->assetCount, $second->name],
        );

        return $group;
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, string> $currentAuthorNames
     *
     * @return array<string, string|int>
     */
    private function createRow(
        array $group,
        AuthorMergeCandidateDto $candidate,
        bool $approved,
        AuthorMergeDecision $decision,
        string $targetIds,
        string $targetNames,
        string $reason,
        array $currentAuthorNames,
    ): array {
        return [
            AuthorMergeCsv::APPROVED => $approved ? AuthorMergeCsv::APPROVED_YES : AuthorMergeCsv::APPROVED_NO,
            AuthorMergeCsv::NAME_NORMALIZED => $candidate->nameNormalized,
            AuthorMergeCsv::GROUP_IDENTIFIER => $this->findGroupIdentifier($candidate),
            AuthorMergeCsv::GROUP_REVIEWED_COUNT => $this->countReviewed($group),
            AuthorMergeCsv::AUTHOR_ID => $candidate->id,
            AuthorMergeCsv::NAME => $candidate->name,
            AuthorMergeCsv::IDENTIFIER => $candidate->identifier,
            AuthorMergeCsv::REVIEWED => $candidate->reviewed ? self::YES : self::NO,
            AuthorMergeCsv::ASSET_COUNT => $candidate->assetCount,
            AuthorMergeCsv::CREATED_AT => $candidate->createdAt,
            AuthorMergeCsv::CREATED_BY => $candidate->createdBy,
            AuthorMergeCsv::CURRENT_AUTHORS => AuthorMergeCsv::joinList(array_map(static fn (string $id): string => $currentAuthorNames[$id] ?? $id, $candidate->currentAuthorIds)),
            AuthorMergeCsv::CHILD_AUTHOR_COUNT => $candidate->childAuthorCount,
            AuthorMergeCsv::TARGET_IDS => $targetIds,
            AuthorMergeCsv::TARGET_NAMES => $targetNames,
            AuthorMergeCsv::REASON => $reason,
            AuthorMergeCsv::DECISION => $decision->value,
        ];
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function countReviewed(array $group): int
    {
        return count(array_filter($group, static fn (AuthorMergeCandidateDto $candidate): bool => $candidate->reviewed));
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function sumAssets(array $group): int
    {
        return array_sum(array_map(static fn (AuthorMergeCandidateDto $candidate): int => $candidate->assetCount, $group));
    }

    /**
     * @param list<AuthorMergeCandidateDto> $candidates
     *
     * @return array<string, string> names of all current authors by id
     */
    private function findCurrentAuthorNames(array $candidates): array
    {
        $names = [];
        $currentAuthorIds = [];
        foreach ($candidates as $candidate) {
            $names[$candidate->id] = $candidate->name;
            array_push($currentAuthorIds, ...$candidate->currentAuthorIds);
        }

        $unknownIds = array_values(array_diff(array_unique($currentAuthorIds), array_keys($names)));

        return $names + $this->authorDBALRepository->findNames($unknownIds);
    }

    /**
     * The identifier a merge group is split by: an empty one or one equal to the name counts as none.
     */
    private function findGroupIdentifier(AuthorMergeCandidateDto $candidate): string
    {
        $identifierNormalized = AuthorNameNormalizer::normalize($candidate->identifier);

        return $identifierNormalized === $candidate->nameNormalized ? App::EMPTY_STRING : $identifierNormalized;
    }
}
