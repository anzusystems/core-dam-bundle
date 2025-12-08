<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\DistributionAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class DistributionAdmElasticsearchDecorator
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
    public function searchInfiniteList(DistributionAdmSearchDto $searchDto): ApiInfiniteResponseList
    {
        $this->validator->validate($searchDto);

        $licence = $searchDto->getLicences()->first();
        if (false === ($licence instanceof AssetLicence)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        return $this->elasticSearch->searchInfiniteList($searchDto, $licence->getExtSystem());
    }
}
