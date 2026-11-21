<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchLicenceCollectionDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\SingleUseUsage;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;

final class AssetAdmElasticsearchDecorator
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly ElasticSearch $elasticSearch,
        private readonly ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws ElasticsearchException
     */
    public function searchInfiniteList(AssetAdmSearchLicenceCollectionDto $searchDto): ApiInfiniteResponseList
    {
        $this->validator->validate($searchDto);

        $licence = $searchDto->getLicences()->first();
        if (false === ($licence instanceof AssetLicence)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        return $this->decorate(
            $this->elasticSearch->searchInfiniteList($searchDto, $licence->getExtSystem()),
            $licence->getExtSystem(),
            $searchDto,
        );
    }

    /**
     * @throws SerializerException
     * @throws ElasticsearchException
     * @throws ValidationException
     */
    public function searchInfiniteListByExtSystem(AssetAdmSearchDto $searchDto, ExtSystem $extSystem): ApiInfiniteResponseList
    {
        $this->validator->validate($searchDto);

        return $this->decorate(
            $this->elasticSearch->searchInfiniteList($searchDto, $extSystem),
            $extSystem,
            $searchDto,
        );
    }

    private function decorate(ApiInfiniteResponseList $list, ExtSystem $extSystem, AssetAdmSearchDto $searchDto): ApiInfiniteResponseList
    {
        /** @var list<Asset> $assets */
        $assets = $list->getData();
        $usageByGroupId = $this->resolveSingleUseUsage($assets, $extSystem, $searchDto);

        return $list->setData(
            array_map(
                function (Asset $asset) use ($usageByGroupId): AssetAdmListDto {
                    $groupId = $this->getSingleUseGroupId($asset);

                    return AssetAdmListDto::getInstance($asset)
                        ->setSingleUseUsage(null === $groupId ? null : ($usageByGroupId[$groupId] ?? null));
                },
                $assets
            )
        );
    }

    /**
     * @param list<Asset> $assets
     *
     * @return array<string, SingleUseUsage>
     */
    private function resolveSingleUseUsage(array $assets, ExtSystem $extSystem, AssetAdmSearchDto $searchDto): array
    {
        if (false === $searchDto->isSingleUseUsage()) {
            return [];
        }

        $groupIds = [];
        foreach ($assets as $asset) {
            $groupId = $this->getSingleUseGroupId($asset);
            if (null !== $groupId) {
                $groupIds[$groupId] = $groupId;
            }
        }
        if ([] === $groupIds) {
            return [];
        }

        return $this->extSystemCallbackFacade->resolveSingleUseUsage(
            slug: $extSystem->getSlug(),
            groupIds: array_values($groupIds),
            holderName: $searchDto->getUsageHolderName(),
            holderId: $searchDto->getUsageHolderId(),
        );
    }

    private function getSingleUseGroupId(Asset $asset): ?string
    {
        $mainFile = $asset->getMainFile();

        return $mainFile?->getFlags()->isSingleUse() ? $mainFile->getTakeOverRootId() : null;
    }
}
