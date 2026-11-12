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
use Normalizer;

/**
 * Step 1 of the merge: proposes groups of duplicate authors for people to check (#87355).
 * A group is the same normalized name and the same identifier, so namesakes with different identifiers are never
 * merged. The main author is the one with the most assets (then reviewed, then older); a member that is already a
 * current author, or that already has current authors set by a person, wins and the others follow it. Groups the
 * rules cannot decide are left to people with an empty decision.
 */
final readonly class AuthorMergePlanFacade
{
    private const string PROPOSAL_MAIN = 'hlavný';
    private const string PROPOSAL_VARIANT = 'podriadený';
    private const string PROPOSAL_KEEP = 'bez zmeny: už má nadriadeného';
    private const string PROPOSAL_MANUAL = 'ručne';
    private const array PROPOSAL_ORDER = [
        self::PROPOSAL_MAIN => 0,
        self::PROPOSAL_VARIANT => 1,
        self::PROPOSAL_MANUAL => 1,
        self::PROPOSAL_KEEP => 2,
    ];
    private const string REASON_MOST_ASSETS = 'najviac fotiek';
    private const string REASON_IS_CURRENT_AUTHOR = 'už je nadriadeným iných autorov';
    private const string REASON_HAS_CURRENT_AUTHORS = 'už má nadriadených, ostatní dostanú tých istých';
    private const string MANUAL_TWO_CURRENT_AUTHORS = 'v skupine sú dvaja alebo viac nadriadení';
    private const string MANUAL_OTHER_CURRENT_AUTHOR = 'člen skupiny má iného nadriadeného ako ten, ktorý je v skupine';
    private const string MANUAL_DIFFERENT_CURRENT_AUTHORS = 'členovia skupiny majú rôznych nadriadených';
    private const string MANUAL_INITIALS = 'iniciály sa líšia len diakritikou, môžu to byť dvaja ľudia';
    private const string MANUAL_UNCOVERED_NAME = 'nadriadení hlavného nepokrývajú celé jeho meno, varianty by prišli o časť';
    private const array MANUAL_VARIANTS = [
        self::MANUAL_INITIALS => 'M1 iniciály sa líšia len diakritikou',
        self::MANUAL_DIFFERENT_CURRENT_AUTHORS => 'M2 členovia majú rôznych nadriadených',
        self::MANUAL_OTHER_CURRENT_AUTHOR => 'M2 členovia majú rôznych nadriadených',
        self::MANUAL_TWO_CURRENT_AUTHORS => 'M3 dvaja nadriadení v jednej skupine',
        self::MANUAL_UNCOVERED_NAME => 'M4 nadriadení nepokrývajú meno',
    ];
    private const string YES = 'áno';
    private const string NO = 'nie';
    private const string LIST_SEPARATOR = ' + ';

    public function __construct(
        private AuthorDBALRepository $authorDBALRepository,
    ) {
    }

    public function writePlan(ExtSystem $extSystem, string $path): AuthorMergePlanResult
    {
        $proposals = $this->createProposals($this->authorDBALRepository->findMergeCandidates((int) $extSystem->getId()));
        $rows = [];
        $variants = App::ZERO;
        foreach ($proposals as $index => $proposal) {
            foreach ($proposal->rows as $row) {
                if (self::PROPOSAL_VARIANT === $row[AuthorMergeCsv::PROPOSAL]) {
                    ++$variants;
                }
                $row[AuthorMergeCsv::GROUP] = $index + 1;
                $row[AuthorMergeCsv::VARIANT] = $proposal->variant;
                $row[AuthorMergeCsv::EXT_SYSTEM] = $extSystem->getSlug();
                $rows[] = array_map(static fn (string $column): string|int => $row[$column], AuthorMergeCsv::COLUMNS);
            }
        }
        AuthorMergeCsv::write($path, $rows);

        return new AuthorMergePlanResult(
            groups: count($proposals),
            manualGroups: count(array_filter($proposals, static fn (AuthorMergeProposalDto $proposal): bool => $proposal->manual)),
            authors: count($rows),
            variants: $variants,
        );
    }

    /**
     * Manual groups first, then by assets of the group, largest first.
     *
     * @param list<AuthorMergeCandidateDto> $candidates
     *
     * @return list<AuthorMergeProposalDto>
     */
    private function createProposals(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate->nameNormalized . "\0" . $candidate->groupIdentifier][] = $candidate;
        }
        $currentAuthorNames = $this->findCurrentAuthorNames($candidates);

        $proposals = [];
        foreach ($groups as $key => $group) {
            if (1 === count($group)) {
                continue;
            }
            $manualReason = $this->findManualReason(group: $group, currentAuthorNames: $currentAuthorNames);
            $proposals[] = null === $manualReason
                ? $this->proposeGroup(key: (string) $key, group: $group, currentAuthorNames: $currentAuthorNames)
                : $this->proposeManualGroup(key: (string) $key, group: $group, reason: $manualReason);
        }
        usort(
            $proposals,
            static fn (AuthorMergeProposalDto $first, AuthorMergeProposalDto $second): int => [$second->manual, $second->assetCount, $first->key] <=> [$first->manual, $first->assetCount, $second->key],
        );

        return $proposals;
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
        if (empty($holders) && 1 === count($currentAuthorSets) && count($this->findMapped($group)) < count($group) && $this->losesNamePart(main: $this->findMostUsed($this->findMapped($group)), currentAuthorNames: $currentAuthorNames)) {
            return self::MANUAL_UNCOVERED_NAME;
        }

        return $this->isInitialsDiacriticsOnly($group) ? self::MANUAL_INITIALS : null;
    }

    /**
     * "Š. Hraško" and "S.Hraško" can be two people: an initial (a single letter with a dot) whose spellings differ
     * only in diacritics is left to people.
     *
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function isInitialsDiacriticsOnly(array $group): bool
    {
        if (false === (bool) preg_match('/\b\w\s*\./u', $group[App::ZERO]->nameNormalized)) {
            return false;
        }

        $spellings = array_unique(array_map(
            static fn (AuthorMergeCandidateDto $candidate): string => (string) preg_replace('/\W/u', App::EMPTY_STRING, mb_strtolower($candidate->name)),
            $group,
        ));
        $ascii = array_unique(array_map(
            static fn (string $spelling): string => (string) preg_replace('/[^\x00-\x7F]/', App::EMPTY_STRING, (string) Normalizer::normalize($spelling, Normalizer::FORM_D)),
            $spellings,
        ));

        return count($spellings) > 1 && 1 === count($ascii);
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     * @param array<string, string> $currentAuthorNames
     */
    private function proposeGroup(string $key, array $group, array $currentAuthorNames): AuthorMergeProposalDto
    {
        $holders = $this->findCurrentAuthorsOfOthers($group);
        $mapped = $this->findMapped($group);
        $main = match (true) {
            false === empty($holders) => $holders[App::ZERO],
            false === empty($mapped) => $this->findMostUsed($mapped),
            default => $this->findMostUsed($group),
        };
        $reason = match (true) {
            false === empty($holders) => self::REASON_IS_CURRENT_AUTHOR,
            false === empty($mapped) => self::REASON_HAS_CURRENT_AUTHORS,
            default => self::REASON_MOST_ASSETS,
        };
        $targetIds = $main->currentAuthorIds ?: [$main->id];
        $targetNames = array_map(static fn (string $id): string => $currentAuthorNames[$id] ?? $id, $targetIds);

        $proposals = [];
        foreach ($group as $candidate) {
            $proposals[$candidate->id] = match (true) {
                $candidate->id === $main->id => self::PROPOSAL_MAIN,
                false === empty($candidate->currentAuthorIds) => self::PROPOSAL_KEEP,
                default => self::PROPOSAL_VARIANT,
            };
        }

        $rows = [];
        foreach ($this->sortMembers(group: $group, proposals: $proposals) as $candidate) {
            $proposal = $proposals[$candidate->id];
            $rows[] = $this->createRow(
                group: $group,
                candidate: $candidate,
                proposal: $proposal,
                targetIds: implode(self::LIST_SEPARATOR, $targetIds),
                targetNames: implode(self::LIST_SEPARATOR, $targetNames),
                reason: $reason,
                decision: match ($proposal) {
                    self::PROPOSAL_MAIN => AuthorMergeDecision::Main,
                    self::PROPOSAL_VARIANT => AuthorMergeDecision::Variant,
                    default => AuthorMergeDecision::NoChange,
                },
            );
        }

        return new AuthorMergeProposalDto(
            manual: false,
            variant: $this->describeGroup(group: $group, main: $main, hasVariants: in_array(self::PROPOSAL_VARIANT, $proposals, true)),
            assetCount: $this->sumAssets($group),
            key: $key,
            rows: $rows,
        );
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     */
    private function proposeManualGroup(string $key, array $group, string $reason): AuthorMergeProposalDto
    {
        $proposals = array_fill_keys(array_map(static fn (AuthorMergeCandidateDto $candidate): string => $candidate->id, $group), self::PROPOSAL_MANUAL);

        return new AuthorMergeProposalDto(
            manual: true,
            variant: self::MANUAL_VARIANTS[$reason],
            assetCount: $this->sumAssets($group),
            key: $key,
            rows: array_map(
                fn (AuthorMergeCandidateDto $candidate): array => $this->createRow(
                    group: $group,
                    candidate: $candidate,
                    proposal: self::PROPOSAL_MANUAL,
                    targetIds: App::EMPTY_STRING,
                    targetNames: App::EMPTY_STRING,
                    reason: $reason,
                    decision: AuthorMergeDecision::Empty,
                ),
                $this->sortMembers(group: $group, proposals: $proposals),
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
            false === $hasVariants => 'A7 skupina je už upratená',
            $main->childAuthorCount > App::ZERO => 'A5 hlavný už je nadriadeným iných',
            false === empty($main->currentAuthorIds) => 'A6 člen už má nadriadených od človeka',
            App::ZERO === $reviewedCount => 'A1 nikto nie je schválený',
            1 === $reviewedCount && $main->reviewed => 'A2 schválený je hlavný',
            1 === $reviewedCount => 'A3 schválený je variant s menej fotkami',
            default => 'A4 viac schválených',
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
     * The variants get the current authors of the main author, so a word of the name they do not carry would vanish
     * from the assets: "Korzár/Michal Frank" under "Michal Frank" loses Korzár.
     *
     * @param array<string, string> $currentAuthorNames
     */
    private function losesNamePart(AuthorMergeCandidateDto $main, array $currentAuthorNames): bool
    {
        $covered = [];
        foreach ($main->currentAuthorIds as $id) {
            array_push($covered, ...$this->splitWords($currentAuthorNames[$id] ?? App::EMPTY_STRING));
        }

        return false === empty(array_diff($this->splitWords($main->name), $covered));
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
     * @param array<string, string> $proposals
     *
     * @return list<AuthorMergeCandidateDto>
     */
    private function sortMembers(array $group, array $proposals): array
    {
        usort(
            $group,
            static fn (AuthorMergeCandidateDto $first, AuthorMergeCandidateDto $second): int => [self::PROPOSAL_ORDER[$proposals[$first->id]], $second->assetCount, $first->name] <=> [self::PROPOSAL_ORDER[$proposals[$second->id]], $first->assetCount, $second->name],
        );

        return $group;
    }

    /**
     * @param list<AuthorMergeCandidateDto> $group
     *
     * @return array<string, string|int>
     */
    private function createRow(
        array $group,
        AuthorMergeCandidateDto $candidate,
        string $proposal,
        string $targetIds,
        string $targetNames,
        string $reason,
        AuthorMergeDecision $decision,
    ): array {
        return [
            AuthorMergeCsv::NAME_NORMALIZED => $candidate->nameNormalized,
            AuthorMergeCsv::GROUP_IDENTIFIER => $candidate->groupIdentifier,
            AuthorMergeCsv::GROUP_REVIEWED_COUNT => $this->countReviewed($group),
            AuthorMergeCsv::AUTHOR_ID => $candidate->id,
            AuthorMergeCsv::NAME => $candidate->name,
            AuthorMergeCsv::IDENTIFIER => $candidate->identifier,
            AuthorMergeCsv::REVIEWED => $candidate->reviewed ? self::YES : self::NO,
            AuthorMergeCsv::ASSET_COUNT => $candidate->assetCount,
            AuthorMergeCsv::CREATED_AT => $candidate->createdAt,
            AuthorMergeCsv::CREATED_BY => $candidate->createdBy,
            AuthorMergeCsv::CURRENT_AUTHOR_COUNT => count($candidate->currentAuthorIds),
            AuthorMergeCsv::CHILD_AUTHOR_COUNT => $candidate->childAuthorCount,
            AuthorMergeCsv::PROPOSAL => $proposal,
            AuthorMergeCsv::CURRENT_AUTHOR_IDS => $targetIds,
            AuthorMergeCsv::CURRENT_AUTHOR_NAMES => $targetNames,
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

        return $names + $this->authorDBALRepository->findNames(array_values(array_diff(array_unique($currentAuthorIds), array_keys($names))));
    }
}
