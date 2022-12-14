<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeApiClient;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Distribution\RemoteProcessingDistributionModuleInterface;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Exception;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use RedisException;

final class YoutubeDistributionModule extends AbstractDistributionModule implements RemoteProcessingDistributionModuleInterface
{
    public function __construct(
        private readonly YoutubeApiClient $client,
        private readonly YoutubeAuthenticator $authenticator,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isAuthenticated(string $distributionService): bool
    {
        return $this->authenticator->isAuthenticated($distributionService);
    }

    /**
     * @param YoutubeDistribution $distribution
     *
     * @throws SerializerException
     * @throws Exception
     * @throws FilesystemException
     * @throws RedisException
     */
    public function distribute(Distribution $distribution): void
    {
        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (null === $assetFile) {
            return;
        }

        $video = $this->client->distribute(
            assetFile: $assetFile,
            distribution: $distribution,
            configuration: $this->distributionConfigurationProvider->getYoutubeDistributionService(
                $distribution->getDistributionService()
            ),
            file: $this->getLocalFileCopy($assetFile)
        );

        if ($video) {
            $distribution->setExtId($video->getId());
            if (false === empty($distribution->getPlaylist())) {
                $this->client->setPlaylist(
                    distributionService: $distribution->getDistributionService(),
                    videoId: $distribution->getExtId(),
                    playlistId: $distribution->getPlaylist()
                );
            }

            return;
        }

        throw new DistributionFailedException();
    }

    public function supportsAssetType(): array
    {
        return [
            AssetType::Video,
        ];
    }

    /**
     * @throws Exception
     */
    public function checkDistributionStatus(Distribution $distribution): void
    {
        $config = $this->distributionConfigurationProvider->getYoutubeDistributionService($distribution->getDistributionService());
        $video = $this->client->getVideo($config, $distribution->getExtId());

        if (null === $video) {
            throw new RemoteProcessingFailedException(message: "Video ({$distribution->getExtId()}) not found in YT");
        }

        if (YoutubeVideoDto::UPLOAD_STATUS_PROCESSED === $video->getUploadStatus()) {
            $distribution->setDistributionData([
                YoutubeDistribution::THUMBNAIL_WIDTH => $video->getThumbnailWidth(),
                YoutubeDistribution::THUMBNAIL_HEIGHT => $video->getThumbnailHeight(),
                YoutubeDistribution::THUMBNAIL_DATA => $video->getThumbnailUrl(),
            ]);

            return;
        }

        throw new RemoteProcessingWaitingException();
    }

    public static function supportsDistributionResourceName(): string
    {
        return YoutubeDistribution::getResourceName();
    }
}
