<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class AssetAdmElasticsearchDecorator
{
    public function __construct(
        private readonly ElasticSearch $elasticSearch,
        private readonly EntityValidator $validator,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     */
    public function searchInfiniteList(AssetAdmSearchDto $searchDto, ExtSystem $extSystem): ApiInfiniteResponseList
    {
        $this->validator->validateDto($searchDto);
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
