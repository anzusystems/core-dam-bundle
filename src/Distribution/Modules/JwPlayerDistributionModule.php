<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwPlayerCustomDataFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoThumbnail;
use AnzuSystems\CoreDamBundle\Distribution\PreviewProvidableModuleInterface;
use AnzuSystems\CoreDamBundle\Distribution\RemoteProcessingDistributionModuleInterface;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\JwMediaStatus;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;
use League\Flysystem\FilesystemException;

final class JwPlayerDistributionModule extends AbstractDistributionModule implements
    RemoteProcessingDistributionModuleInterface,
    PreviewProvidableModuleInterface
{
    private const REMOTE_PROCESS_WAIT_TRESHOLD = '+1 hour';

    public function __construct(
        private readonly JwVideoClient $jwVideoClient,
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
        private readonly JwPlayerCustomDataFactory $customDataFactory,
        private readonly JwVideoThumbnail $jwVideoThumbnail,
        private readonly JwVideoImagePreviewFactory $jwVideoImagePreviewFactory,
    ) {
    }

    /**
     * @param JwDistribution $distribution
     *
     * @throws FilesystemException
     * @throws JsonException
     * @throws SerializerException
     */
    public function distribute(Distribution $distribution): void
    {
        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (null === $assetFile) {
            return;
        }

        $createVideoDto = $this->jwVideoClient->createVideoObject(
            $this->distributionConfigurationProvider->getJwDistributionService($distribution->getDistributionService()),
            $this->jwVideoDtoFactory->createVideoDtoFromJwVideo($assetFile, $distribution),
        );
        $distribution->setExtId($createVideoDto->getId());

        $file = $this->getLocalFileCopy($assetFile);
        $this->jwVideoClient->uploadVideoObject($createVideoDto, $file);

        $url = $this->jwVideoImagePreviewFactory->getThumbnailUrl($assetFile);
        if ($url) {
            $this->jwVideoThumbnail->setVideoThumbnail(
                imageUrl: $url,
                videoId: $createVideoDto->getId(),
                distribService: $distribution->getDistributionService()
            );
        }
    }

    /**
     * @param JwDistribution $distribution
     *
     * @throws SerializerException
     * @throws JsonException
     */
    public function checkDistributionStatus(Distribution $distribution): void
    {
        $video = $this->jwVideoClient->getVideoObject(
            $this->distributionConfigurationProvider->getJwDistributionService($distribution->getDistributionService()),
            $distribution
        );
        if ($video->getStatus()->is(JwMediaStatus::Ready)) {
            $distribution->setDistributionData($this->customDataFactory->createDistributionData($distribution));

            return;
        }
        if ($video->getStatus()->is(JwMediaStatus::Failed)) {
            throw new RemoteProcessingFailedException(message: (string) $video->getErrorMessage());
        }

        $waitUntilDatetime = App::getAppDate()->modify(self::REMOTE_PROCESS_WAIT_TRESHOLD);
        if ($waitUntilDatetime < App::getAppDate()) {
            throw new RemoteProcessingFailedException(message: 'Remote processing too long.');
        }

        throw new RemoteProcessingWaitingException();
    }

    public function supportsAssetType(): array
    {
        return [
            AssetType::Video,
        ];
    }

    public static function supportsDistributionResourceName(): string
    {
        return JwDistribution::getResourceName();
    }

    public function getPreviewLink(Distribution $distribution): ?string
    {
        if ($distribution->getStatus()->is(DistributionProcessStatus::Distributed)) {
            return $this->customDataFactory->getUrl($distribution);
        }

        return null;
    }
}
