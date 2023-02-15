<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class AudioRouteGenerator
{
    public function __construct(
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function getFullUrl(
        string $path,
        string $extSlug
    ): string {
        $config = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration(extSystemSlug: $extSlug);

        return UrlHelper::concatPathWithDomain(
            $config->getPublicDomainName(),
            $path
        );
    }
}
