<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeApiClient;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeCustomDataFactory;
use AnzuSystems\CoreDamBundle\Distribution\PreviewProvidableModuleInterface;
use AnzuSystems\CoreDamBundle\Distribution\RemoteProcessingDistributionModuleInterface;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFacade;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Exception;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use Throwable;

final class YoutubeDistributionModule extends AbstractDistributionModule implements
    RemoteProcessingDistributionModuleInterface,
    PreviewProvidableModuleInterface
{
    private const string YOUTUBE_DISTRIBUTION_TAG = 'youtube_distribution';

    public function __construct(
        private readonly YoutubeApiClient $client,
        private readonly YoutubeAuthenticator $authenticator,
        private readonly DamLogger $logger,
        private readonly YoutubeCustomDataFactory $youtubeCustomDataFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CropFacade $cropFacade,
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
     * @throws Exception
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function distribute(Distribution $distribution): void
    {
        /** @var VideoFile $assetFile */
        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (false === ($assetFile instanceof VideoFile)) {
            return;
        }
        $file = $this->getLocalFileCopy($assetFile);
        $video = $this->client->distribute(
            assetFile: $assetFile,
            distribution: $distribution,
            configuration: $this->distributionConfigurationProvider->getYoutubeDistributionService(
                $distribution->getDistributionService()
            ),
            file: $file
        );

        if ($video) {
            $distribution->setExtId($video->getId());

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
     * @param YoutubeDistribution $distribution
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function checkDistributionStatus(Distribution $distribution): void
    {
        $config = $this->distributionConfigurationProvider->getYoutubeDistributionService($distribution->getDistributionService());
        $video = $this->client->getVideo($config, $distribution->getExtId());

        if (null === $video) {
            throw new RemoteProcessingFailedException(message: "Video ({$distribution->getExtId()}) not found in YT");
        }

        if (YoutubeVideoDto::UPLOAD_STATUS_PROCESSED === $video->getUploadStatus()) {
            $distribution->setDistributionData($this->youtubeCustomDataFactory->createDistributionData($video));
            $this->updatePreviewAndPlaylist($distribution);

            return;
        }

        throw new RemoteProcessingWaitingException();
    }

    public static function supportsDistributionResourceName(): string
    {
        return YoutubeDistribution::getResourceName();
    }

    public function getPreviewLink(Distribution $distribution): ?string
    {
        if ($distribution->getStatus()->is(DistributionProcessStatus::Distributed)) {
            return $this->youtubeCustomDataFactory->getUrl($distribution);
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException
     * @throws SerializerException
     * @throws Exception
     */
    private function updatePreviewAndPlaylist(YoutubeDistribution $distribution): void
    {
        if (false === empty($distribution->getPlaylist())) {
            $this->client->setPlaylist(
                distributionService: $distribution->getDistributionService(),
                videoId: $distribution->getExtId(),
                playlistId: $distribution->getPlaylist()
            );
        }

        try {
            $this->setThumbnail($distribution);
        } catch (Throwable $e) {
            $this->logger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('YT set thumbnail failed (%s) error (%s)', $distribution->getAssetFileId(), $e->getMessage())
            );
        }
    }

    private function setThumbnail(YoutubeDistribution $distribution): void
    {
        /** @var VideoFile $video */
        $video = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (false === ($video instanceof VideoFile)) {
            return;
        }

        $imageFile = $video->getImagePreview()?->getImageFile();
        if (null === $imageFile || $imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return;
        }

        $cropItem = $this->configurationProvider->getFirstCropAllowItemByTag(self::YOUTUBE_DISTRIBUTION_TAG);
        if (null === $cropItem) {
            $this->logger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('Youtube thumbnail update failed, crop allow item with tag (%s) not found', self::YOUTUBE_DISTRIBUTION_TAG)
            );

            return;
        }

        $this->client->setThumbnail(
            distributionService: $distribution->getDistributionService(),
            distributionId: $distribution->getExtId(),
            imageFile: $imageFile,
            imageData: $this->cropFacade->applyCropPayloadToDefaultRoi(
                image: $imageFile,
                cropPayload: (new RequestedCropDto())
                    ->setRoi(App::ZERO)
                    ->setRequestHeight($cropItem->getHeight())
                    ->setRequestWidth($cropItem->getWidth()),
                validate: false
            )
        );
    }
}
