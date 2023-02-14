<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use InvalidArgumentException;

final class AudioRouteGenerator
{
    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function getFullUrl(AudioFile $audioFile): string
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetFile($audioFile);
        if (false === ($config instanceof ExtSystemAudioTypeConfiguration)) {
            throw new InvalidArgumentException('Asset type must be a type of audio');
        }

        return $config->getPublicDomainName() . '/' . $audioFile->getAudioPublicLink()->getPath();
    }
}
