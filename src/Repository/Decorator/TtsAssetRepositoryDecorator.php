<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;

final class TtsAssetRepositoryDecorator
{
    public function __construct(
        private readonly TtsAssetRepository $ttsAssetRepository,
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly PodcastEpisodeRepository $episodeRepository,
    ) {
    }

    public function getDetail(Asset $asset): TtsAudioAdmDetailDto
    {
        return TtsAudioAdmDetailDto::getInstance(
            $asset,
            $this->ttsAssetRepository->findByAsset($asset),
            $this->requestRepository->findLastIdByAsset((string) $asset->getId()),
            $this->episodeRepository->findPodcastIdsByAsset($asset),
        );
    }
}
