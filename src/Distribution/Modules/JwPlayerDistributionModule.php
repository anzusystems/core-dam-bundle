<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Distribution\PreviewProvidableModuleInterface;
use AnzuSystems\CoreDamBundle\Distribution\RemoteProcessingDistributionModuleInterface;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\JwMediaStatus;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;

final class JwPlayerDistributionModule extends AbstractDistributionModule implements
    RemoteProcessingDistributionModuleInterface,
    PreviewProvidableModuleInterface
{
    public function __construct(
        private readonly JwVideoClient $jwVideoClient,
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
        private readonly JwPlayerCustomDataFactory $customDataFactory,
        private readonly DamLogger $logger,
    ) {
    }

    /**
     * @param JwDistribution $distribution
     *
     * @throws SerializerException
     * @throws FilesystemException
     */
    public function distribute(Distribution $distribution): void
    {
        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (null === $assetFile) {
            return;
        }

        $this->logger->info(DamLogger::NAMESPACE_DISTRIBUTION, sprintf('JW creating video object (%s)', $assetFile->getId()));
        $createVideoDto = $this->jwVideoClient->createVideoObject(
            $this->distributionConfigurationProvider->getJwDistributionService($distribution->getDistributionService()),
            $this->jwVideoDtoFactory->createVideoDtoFromJwVideo($assetFile, $distribution),
        );
        $distribution->setExtId($createVideoDto->getId());

        $this->logger->info(DamLogger::NAMESPACE_DISTRIBUTION, sprintf('JW getting local file copy (%s)', $assetFile->getId()));
        $file = $this->getLocalFileCopy($assetFile);
        $this->logger->info(DamLogger::NAMESPACE_DISTRIBUTION, sprintf('JW upoloading (%s)', $assetFile->getId()));
        $this->jwVideoClient->uploadVideoObject($createVideoDto, $file);
        $this->logger->info(DamLogger::NAMESPACE_DISTRIBUTION, sprintf('JW video uploaded (%s)', $assetFile->getId()));
    }

    /**
     * @param JwDistribution $distribution
     *
     * @throws SerializerException
     * @throws RemoteProcessingFailedException
     * @throws RemoteProcessingWaitingException
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
