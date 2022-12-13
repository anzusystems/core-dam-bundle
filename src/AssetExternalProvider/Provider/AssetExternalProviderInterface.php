<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\AssetExternalProvider\Provider;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\AssetExternalProviderDto;

interface AssetExternalProviderInterface
{
    public function setConfiguration(array $config): void;

    public function getConfiguration(): AssetExternalProviderConfiguration;

    /**
     * @return ApiInfiniteResponseList<AssetExternalProviderDto>
     */
    public function search(AssetExternalProviderApiParams $apiParams): ApiInfiniteResponseList;

    /**
     * @param list<string> $ids
     *
     * @return ApiResponseList<AssetExternalProviderDto>
     */
    public function getByIds(array $ids): ApiResponseList;

    public function getById(string $id): AssetExternalProviderDto;

    /**
     * @return resource
     */
    public function download(string $id);
}
