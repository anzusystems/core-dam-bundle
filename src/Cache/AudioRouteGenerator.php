<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;

final readonly class AudioRouteGenerator
{
    public function __construct(
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function getFullUrl(
        AudioFile $audioFile
    ): string {
        if (null === $audioFile->getRoute()) {
            return '';
        }
        $config = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration($audioFile->getExtSystem()->getSlug());

        return UrlHelper::concatPathWithDomain(
            $config->getPublicDomainName(),
            $audioFile->getRoute()->getPath()
        );
    }
}
