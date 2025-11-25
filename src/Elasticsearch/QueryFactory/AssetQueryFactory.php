<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchLicenceCollectionDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

final class AssetQueryFactory extends AbstractQueryFactory
{
    protected const array BOOST_FIELDS = [
        StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => [
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => 5,
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY . '.edgegrams' => 1,
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY . '.lang' => 1,
        ],
        StringIndexBuilder::CUSTOM_DESCRIPTION_KEY => [
            StringIndexBuilder::CUSTOM_DESCRIPTION_KEY . '.lang' => 1,
        ],
    ];

    private const string CUSTOM_SORT_DATE_FIELD = 'createdAt';
    private const string CUSTOM_SORT_DATE_DECAY_FUNCTION = 'exp';
    private const string CUSTOM_SORT_DATE_DECAY_ORIGIN = 'now';
    private const string CUSTOM_SORT_DATE_DECAY_SCALE = '60d';
    private const string CUSTOM_SORT_DATE_DECAY_OFFSET = '14d';
    private const float CUSTOM_SORT_DATE_DECAY_DECAY = 0.5;

    public function __construct(
        private readonly CustomFormProvider $customFormProvider,
        private bool $searcNext = true,
    ) {
    }

    public function getSupportedSearchDtoClasses(): array
    {
        return [
            AssetAdmSearchDto::class,
            AssetAdmSearchLicenceCollectionDto::class,
        ];
    }

    /**
     * @param AssetAdmSearchDto $searchDto
     */
    public function getScriptScoreFunction(SearchDtoInterface $searchDto): ?array
    {
        if (false === $this->searcNext || false === $this->isFulltextSearch($searchDto)) {
            return null;
        }

        return [
            [
                self::CUSTOM_SORT_DATE_DECAY_FUNCTION => [
                    self::CUSTOM_SORT_DATE_FIELD => [
                        'origin' => self::CUSTOM_SORT_DATE_DECAY_ORIGIN,
                        'scale' => self::CUSTOM_SORT_DATE_DECAY_SCALE,
                        'offset' => self::CUSTOM_SORT_DATE_DECAY_OFFSET,
                        'decay' => self::CUSTOM_SORT_DATE_DECAY_DECAY,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param AssetAdmSearchDto $searchDto
     */
    public function isFulltextSearch(SearchDtoInterface $searchDto): bool
    {
        return StringHelper::isNotEmpty($searchDto->getText());
    }

    protected function expandFulltextOrderFields(string $field, string $direction): array
    {
        if (false === $this->searcNext) {
            return parent::expandFulltextOrderFields($field, $direction);
        }

        return match ($field) {
            self::CUSTOM_ORDER_SCORE_DATE,
            self::CUSTOM_ORDER_SCORE_BEST => [self::SCORE_ORDER => $direction],
            default => [$field => $direction],
        };
    }

    protected function expandRegularOrderFields(string $field, string $direction): array
    {
        return match ($field) {
            self::CUSTOM_ORDER_SCORE_BEST,
            self::CUSTOM_ORDER_SCORE_DATE => [self::CUSTOM_SORT_DATE_FIELD => $direction],
            default => [$field => $direction],
        };
    }

    /**
     * @param AssetAdmSearchDto $searchDto
     */
    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $customDataFields = $this->customFormProvider->provideAllSearchableElementsForExtSystem($extSystem->getSlug())->map(
            fn (CustomFormElement $element): string => CustomDataIndexDefinitionFactory::getIndexKeyNameByElement($element)
        )->toArray();

        $customDataFields = array_reverse(array_values(array_unique($customDataFields)));

        if (
            StringHelper::isNotEmpty($searchDto->getCustomDataKey()) &&
            StringHelper::isNotEmpty($searchDto->getCustomDataValue())

        ) {
            $customDataKey = CustomDataIndexDefinitionFactory::getIndexKeyNameByProperty($searchDto->getCustomDataKey());
            if (in_array($customDataKey, $customDataFields, true)) {
                return [
                    'match' => [
                        $customDataKey => [
                            'query' => $searchDto->getCustomDataValue(),
                        ],
                    ],
                ];
            }
        }

        if (is_string($searchDto->getIdInText())) {
            return parent::getMust($searchDto, $extSystem);
        }

        if ($searchDto->getText()) {
            return [
                'multi_match' => [
                    'query' => $searchDto->getText(),
                    'fields' => $this->boostSearchFields($customDataFields),
                    'type' => 'most_fields',
                    'tie_breaker' => 0.3,
                ],
            ];
        }

        return parent::getMust($searchDto, $extSystem);
    }

    /**
     * @param AssetAdmSearchDto $searchDto
     *
     * @psalm-suppress PossiblyNullReference
     */
    protected function getFilter(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $filter = [];
        if ($searchDto instanceof AssetAdmSearchLicenceCollectionDto) {
            $this->applyLicenceCollectionFilter($filter, $searchDto);
        }

        if (is_string($searchDto->getIdInText())) {
            $filter[] = $this->getAssetIdAndMainFileIdFilter([$searchDto->getIdInText()]);

            // other filters should not be applied
            return $filter;
        }

        if (false === empty($searchDto->getAssetAndMainFileIds())) {
            $filter[] = $this->getAssetIdAndMainFileIdFilter($searchDto->getAssetAndMainFileIds());

            // other filters should not be applied
            return $filter;
        }

        if (false === (null === $searchDto->isVisible())) {
            $filter[] = ['terms' => ['visible' => [$searchDto->isVisible()]]];
        }
        if (false === (null === $searchDto->isFromRss())) {
            $filter[] = ['terms' => ['fromRss' => [$searchDto->isFromRss()]]];
        }
        if (false === empty($searchDto->getDistributedInServices())) {
            $filter[] = ['terms' => ['distributedInServices' => $searchDto->getDistributedInServices()]];
        }
        if (false === empty($searchDto->getSlotNames())) {
            $filter[] = ['terms' => ['slotNames' => $searchDto->getSlotNames()]];
        }
        if (false === (null === $searchDto->isGeneratedBySystem())) {
            $filter[] = ['terms' => ['generatedBySystem' => [$searchDto->isGeneratedBySystem()]]];
        }
        if (false === (null === $searchDto->isInPodcast())) {
            $filter[] = ['terms' => ['inPodcast' => [$searchDto->isInPodcast()]]];
        }
        if (false === (null === $searchDto->isDescribed())) {
            $filter[] = ['terms' => ['described' => [$searchDto->isDescribed()]]];
        }
        if (false === empty($searchDto->getStatus())) {
            $filter[] = ['terms' => ['status' => $searchDto->getStatus()]];
        }
        if (false === empty($searchDto->getType())) {
            $filter[] = ['terms' => ['type' => $searchDto->getType()]];
        }
        if (false === empty($searchDto->getCodecName())) {
            $filter[] = ['terms' => ['codecName' => $searchDto->getCodecName()]];
        }
        if (false === empty($searchDto->getOrientation())) {
            $filter[] = ['terms' => ['orientation' => $searchDto->getOrientation()]];
        }
        if (false === empty($searchDto->getClosestMostDominantColor())) {
            $filter[] = ['terms' => ['closestMostDominantColor' => $searchDto->getClosestMostDominantColor()]];
        }
        if (false === empty($searchDto->getMostDominantColor())) {
            $filter[] = ['terms' => ['mostDominantColor' => $searchDto->getMostDominantColor()]];
        }
        if (false === empty($searchDto->getPodcastIds())) {
            $filter[] = ['terms' => ['podcastIds.podcastId' => $searchDto->getPodcastIds()]];
        }
        if (false === empty($searchDto->getAssetIds())) {
            $filter[] = ['terms' => ['fileIds' => $searchDto->getAssetIds()]];
        }
        if (false === empty($searchDto->getMainFileIds())) {
            $filter[] = ['terms' => ['mainFileId' => $searchDto->getMainFileIds()]];
        }
        if (false === empty($searchDto->getKeywordIds())) {
            $filter[] = ['terms' => ['keywordIds.keywordId' => $searchDto->getKeywordIds()]];
        }
        if (false === empty($searchDto->getAuthorIds())) {
            $filter[] = ['terms' => ['authorIds.authorId' => $searchDto->getAuthorIds()]];
        }
        if (false === empty($searchDto->getCreatedByIds())) {
            $filter[] = ['terms' => ['createdById' => $searchDto->getCreatedByIds()]];
        }
        if (is_bool($searchDto->isMainFileSingleUse())) {
            $filter[] = ['term' => ['mainFileSingleUse' => $searchDto->isMainFileSingleUse()]];
        }

        $this->applyRangeFilter($filter, 'pixelSize', $searchDto->getPixelSizeFrom(), $searchDto->getPixelSizeUntil());
        $this->applyRangeFilter($filter, 'ratioWidth', $searchDto->getRatioWidthFrom(), $searchDto->getRatioWidthUntil());
        $this->applyRangeFilter($filter, 'ratioHeight', $searchDto->getRatioHeightFrom(), $searchDto->getRatioHeightUntil());
        $this->applyRangeFilter($filter, 'width', $searchDto->getWidthFrom(), $searchDto->getWidthUntil());
        $this->applyRangeFilter($filter, 'height', $searchDto->getHeightFrom(), $searchDto->getHeightUntil());
        $this->applyRangeFilter($filter, 'rotation', $searchDto->getRotationFrom(), $searchDto->getRotationUntil());
        $this->applyRangeFilter($filter, 'duration', $searchDto->getDurationFrom(), $searchDto->getDurationUntil());
        $this->applyRangeFilter($filter, 'bitrate', $searchDto->getBitrateFrom(), $searchDto->getBitrateUntil());
        $this->applyRangeFilter($filter, 'slotsCount', $searchDto->getSlotsCountFrom(), $searchDto->getSlotsCountUntil());
        $this->applyRangeFilter($filter, 'createdAt', $searchDto->getCreatedAtFrom()?->getTimestamp(), $searchDto->getCreatedAtUntil()?->getTimestamp());

        return $filter;
    }

    /**
     * @param array<int, string> $customDataFields
     */
    private function boostSearchFields(array $customDataFields): array
    {
        if ($this->searcNext) {
            $searchFields = [];
            foreach ($customDataFields as $field) {
                if (isset(self::BOOST_FIELDS[$field])) {
                    foreach (self::BOOST_FIELDS[$field] as $boostField => $boost) {
                        $searchFields[] = $boostField . '^' . $boost;
                    }

                    continue;
                }

                $searchFields[] = $field;
            }

            return $searchFields;
        }

        foreach ($customDataFields as $key => $field) {
            $customDataFields[$key] = $field . '^' . ($key + 1);
        }

        return $customDataFields;
    }

    private function getAssetIdAndMainFileIdFilter(array $ids): array
    {
        return [
            'bool' => [
                'should' => [
                    ['terms' => ['id' => $ids]],
                    ['terms' => ['mainFileId' => $ids]],
                ],
            ],
        ];
    }

    private function applyLicenceCollectionFilter(array &$filter, AssetAdmSearchLicenceCollectionDto $dto): void
    {
        if ($dto->getLicences()->isEmpty()) {
            return;
        }

        if (1 === $dto->getLicences()->count()) {
            $licence = $dto->getLicences()->first();

            $filter[] = ['terms' => ['licence' => [(int) $licence->getId()]]];

            return;
        }

        $terms = [];
        foreach ($dto->getLicences() as $licenceId) {
            $terms[] = ['term' => ['licence' => $licenceId->getId()]];
        }
        $filter[] = [
            'bool' => [
                'should' => $terms,
            ],
        ];
    }

    private function applyRangeFilter(array &$filter, string $key, ?int $from, ?int $until): void
    {
        $range = [];
        if ($from) {
            $range['gte'] = $from;
        }
        if ($until) {
            $range['lte'] = $until;
        }

        if (false === empty($range)) {
            $filter[]['range'] = [$key => $range];
        }
    }
}
