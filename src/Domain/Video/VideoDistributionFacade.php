<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Distribution\DistributionImagePreviewAdmDto;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\ORM\Exception\ORMException;
use RuntimeException;
use Throwable;

final class VideoDistributionFacade
{
    use IndexManagerAwareTrait;
    use MessageBusAwareTrait;

    public function __construct(
        private readonly ModuleProvider $moduleProvider,
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly VideoManager $videoManager,
        private readonly DistributionRepository $distributionRepository,
    ) {
    }

    public function setDistributionPreview(VideoFile $video, Distribution $distribution): ImageFile
    {
        $module = $this->moduleProvider->providePreviewProvidableModule($distribution->getDistributionService());

        if (null === $module) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        $link = $module->getPreviewLink($distribution);
        if (null === $link) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        try {
            $this->videoManager->beginTransaction();
            $imageFile = $this->imageDownloadFacade->downloadSynchronous($video->getLicence(), $link);
            $this->videoManager->setImagePreview($video, $imageFile);
            $this->videoManager->commit();

            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $video->getAsset()->getId()));

            return $imageFile;
        } catch (Throwable $e) {
            $this->videoManager->rollback();

            throw new RuntimeException('video_preview_failed', 0, $e);
        }
    }

    /**
     * @throws ORMException
     */
    public function getPreview(ApiParams $apiParams, VideoFile $videoFile): ApiResponseList
    {
        $responseList = $this->distributionRepository->findByApiParamsByAssetFile($apiParams, $videoFile);

        $previewList = [];
        /** @var Distribution $distribution */
        foreach ($responseList->getData() as $distribution) {
            $module = $this->moduleProvider->providePreviewProvidableModule($distribution->getDistributionService());
            $link = $module?->getPreviewLink($distribution);
            if ($link) {
                $previewList[] = DistributionImagePreviewAdmDto::getFromDistribution($distribution)->setUrl($link);
            }
        }

        return (new ApiResponseList())
            ->setData($previewList)
            ->setTotalCount(count($previewList));
    }
}
