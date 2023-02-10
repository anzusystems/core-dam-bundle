<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

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
     */
    public function searchInfiniteList(AssetAdmSearchDto $searchDto, ExtSystem $extSystem): ApiInfiniteResponseList
    {
        $this->validator->validate($searchDto);
        $list = $this->elasticSearch->searchInfiniteList($searchDto, $extSystem);

        return $list
            ->setData(
                array_map(
                    fn (Asset $asset): AssetAdmListDto => AssetAdmListDto::getInstance($asset),
                    $list->getData()
                )
            );
    }
}
