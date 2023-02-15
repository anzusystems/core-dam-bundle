<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class ImageRouteGenerator
{
    public function __construct(
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private AllowListConfiguration $allowListConfiguration,
        private ImageUrlFactory $imageUrlFactory,
        protected ConfigurationProvider $configurationProvider,
    ) {
    }

    public function generateAllPaths(string $extSystemSlug, string $imageId, array $roiPositions = []): array
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetType(
            AssetType::Image,
            $extSystemSlug
        );

        return array_merge(
            $this->generateDomainPaths($config->getAdminDomain(), $imageId, $roiPositions),
            $this->generateDomainPaths($config->getPublicDomain(), $imageId, $roiPositions),
        );
    }

    public function generateAllPublicDomainPaths(string $extSystemSlug, string $imageId, array $roiPositions = []): array
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetType(
            AssetType::Image,
            $extSystemSlug
        );

        return $this->generateDomainPaths($config->getPublicDomain(), $imageId, $roiPositions);
    }

    public function generateAdminRouteByTag(string $imageId, string $extSystemSlug, string $tag, ?int $roiPosition): string
    {
        $sizeList = $this->configurationProvider->getImageAdminSizeList($tag);
        if (empty($sizeList)) {
            return '';
        }

        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetType(
            AssetType::Image,
            $extSystemSlug
        );

        return UrlHelper::concatPathWithDomain(
            $config->getAdminDomain(),
            $this->imageUrlFactory->generateAllowListUrl(
                imageId: $imageId,
                item: $sizeList[0],
                roiPosition: $roiPosition,
            )
        );
    }

    private function generateDomainPaths(string $domain, string $imageId, array $roiPositions = []): array
    {
        $paths = [];
        $list = $this->allowListConfiguration->getListByDomain($domain);
        /** @var array<int, int|null> $qualityList */
        $qualityList = [null, ...$list->getQualityAllowList()];
        // todo add null only if 0 is in array
        /** @var array<int, int|null> $roiList */
        $roiList = [null, ...$roiPositions];

        foreach ($list->getCrops() as $crop) {
            foreach ($roiList as $roiPosition) {
                foreach ($qualityList as $quality) {
                    $paths[] = $this->generatePath(
                        imageId: $imageId,
                        domain: $domain,
                        width: $crop['width'],
                        height: $crop['height'],
                        roiPosition: $roiPosition,
                        quality: $quality
                    );
                }
            }
        }

        return $paths;
    }

    private function generatePath(
        string $imageId,
        string $domain,
        int $width,
        int $height,
        ?int $roiPosition = null,
        ?int $quality = null
    ): string {
        return UrlHelper::concatPathWithDomain(
            $domain,
            $this->imageUrlFactory->generatePublicUrl(
                imageId: $imageId,
                width: $width,
                height: $height,
                roiPosition: $roiPosition,
                quality: $quality
            )
        );
    }
}
