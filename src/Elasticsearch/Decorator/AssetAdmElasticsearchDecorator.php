<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchLicenceCollectionDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;

final class AssetAdmElasticsearchDecorator
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly ElasticSearch $elasticSearch,
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
            $this->elasticSearch->searchInfiniteList($searchDto, $licence->getExtSystem())
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
            $this->elasticSearch->searchInfiniteList($searchDto, $extSystem)
        );
    }

    private function decorate(ApiInfiniteResponseList $list): ApiInfiniteResponseList
    {
        return $list->setData(
            array_map(
                fn (Asset $asset): AssetAdmListDto => AssetAdmListDto::getInstance($asset),
                $list->getData()
            )
        );
    }
}
