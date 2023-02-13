<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

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
        ];
    }

    /**
     * @param AssetAdmSearchDto $searchDto
     */
    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $customDataFields = $this->customFormProvider->provideAllSearchableElementsForExtSystem($extSystem->getSlug())->map(
            fn (CustomFormElement $element): string => CustomDataIndexDefinitionFactory::getIndexKeyName($element)
        )->toArray();

        $customDataFields = array_unique($customDataFields);
        $customDataFields = array_merge($customDataFields, ['title']);

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
    protected function getFilter(SearchDtoInterface $searchDto): array
    {
        $filter = [];

        if (false === (null === $searchDto->isVisible())) {
            $filter[] = ['terms' => ['visible' => [$searchDto->isVisible()]]];
        }
        if (false === (null === $searchDto->isFromRss())) {
            $filter[] = ['terms' => ['from_rss' => [$searchDto->isFromRss()]]];
        }
        if (false === empty($searchDto->getDistributedInServices())) {
            $filter[] = ['terms' => ['distributed_in_services' => $searchDto->getDistributedInServices()]];
        }
        if (false === empty($searchDto->getSlotNames())) {
            $filter[] = ['terms' => ['slot_names' => $searchDto->getSlotNames()]];
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
            $filter[] = ['terms' => ['podcastIds' => $searchDto->getPodcastIds()]];
        }

        $this->applyRangeFilter($filter, 'pixelSize', $searchDto->getPixelSizeFrom(), $searchDto->getPixelSizeUntil());
        $this->applyRangeFilter($filter, 'ratioWidth', $searchDto->getRatioWidthFrom(), $searchDto->getRatioWidthUntil());
        $this->applyRangeFilter($filter, 'ratioHeight', $searchDto->getRatioHeightFrom(), $searchDto->getRatioHeightUntil());
        $this->applyRangeFilter($filter, 'width', $searchDto->getWidthFrom(), $searchDto->getWidthUntil());
        $this->applyRangeFilter($filter, 'height', $searchDto->getHeightFrom(), $searchDto->getHeightUntil());
        $this->applyRangeFilter($filter, 'rotation', $searchDto->getRotationFrom(), $searchDto->getRotationUntil());
        $this->applyRangeFilter($filter, 'duration', $searchDto->getDurationFrom(), $searchDto->getDurationUntil());
        $this->applyRangeFilter($filter, 'bitrate', $searchDto->getBitrateFrom(), $searchDto->getBitrateUntil());
        $this->applyRangeFilter($filter, 'createdAt', $searchDto->getCreatedAtFrom()?->getTimestamp(), $searchDto->getCreatedAtUntil()?->getTimestamp());
        $this->applyRangeFilter($filter, 'pixels', $searchDto->getPixelsFrom(), $searchDto->getPixelsUntil());
        $this->applyRangeFilter($filter, 'shortest_dimension', $searchDto->getShortestDimensionFrom(), $searchDto->getShortestDimensionUntil());

        if (false === empty($searchDto->getLicences())) {
            $filter[] = ['terms' => ['licence' => array_map(
                fn (AssetLicence $assetLicence): int => $assetLicence->getId(),
                $searchDto->getLicences()
            )]];
        }

        return $filter;
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
