<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaCdnDto;
use AnzuSystems\CoreDamBundle\Model\Enum\VideoMimeTypes;

final readonly class JwDirectSourceUrlProvider
{
    public function __construct(
        private JwVideoClient $jwVideoClient,
    ) {
    }

    public function provideDirectSourceUrl(JwDistributionServiceConfiguration $config, JwDistribution $distribution): void
    {
        $videoCdnData = $this->jwVideoClient->getVideoAssetData($config, $distribution->getExtId());
        $distribution->setDirectSourceUrl((string) $this->findBestDirectSourceUrl($videoCdnData));
    }

    private function findBestDirectSourceUrl(JwVideoMediaCdnDto $dto): ?string
    {
        $bestUrl = null;
        $bestQuality = 0;
        foreach ($dto->getPlaylist() as $playlistItem) {
            foreach ($playlistItem->getSources() as $source) {
                $quality = $source->getWidth() * $source->getHeight();
                if (VideoMimeTypes::MIME_MP4 === $source->getType() && $quality > $bestQuality) {
                    $bestUrl = $source->getFile();
                    $bestQuality = $quality;
                }
            }
        }

        return $bestUrl;
    }
}
