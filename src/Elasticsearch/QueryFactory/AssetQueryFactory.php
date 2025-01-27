<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchLicenceCollectionDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

final class AssetQueryFactory extends AbstractQueryFactory
{
    public function __construct(
        private readonly CustomFormProvider $customFormProvider
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

        foreach ($customDataFields as $key => $field) {
            $customDataFields[$key] = $field . '^' . ($key + 1);
        }

        if ($searchDto->getText()) {
            return [
                'multi_match' => [
                    'query' => $searchDto->getText(),
                    'fields' => $customDataFields,
                    'type' => 'most_fields',
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
        //
        //        if (
        //            StringHelper::isNotEmpty($searchDto->getCustomDataKey()) &&
        //            StringHelper::isNotEmpty($searchDto->getCustomDataValue())
        //        ) {
        //            // TODO cache this
        //            $customDataFields = $this->customFormProvider->provideAllSearchableElementsForExtSystem($extSystem->getSlug())->map(
        //                fn (CustomFormElement $element): string => CustomDataIndexDefinitionFactory::getIndexKeyNameByElement($element)
        //            )->toArray();
        //            $customDataFields = array_unique($customDataFields);
        //
        //            if (in_array($searchDto->getCustomDataKey(), $customDataFields, true)) {
        //                $filter[] = ['term' => [$searchDto->getCustomDataKey() => $searchDto->getCustomDataValue()]];
        //            }
        //        }

        //        dd($filter);

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
            if (false === $licence instanceof AssetLicence) {
                return;
            }

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
