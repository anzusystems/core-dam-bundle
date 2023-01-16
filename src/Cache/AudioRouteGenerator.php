<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;

final class AudioRouteGenerator
{
    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function getFullUrl(AudioFile $audioFile): string
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetFile($audioFile);

        return $config->getPublicDomainName() . '/' . $audioFile->getAudioPublicLink()->getPath();
    }
}
