<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFacade;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/image', name: 'image_')]
final class ImageController extends AbstractImageController
{
    public function __construct(
        private readonly CropFacade $cropFacade,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly RegionOfInterestRepository $roiRepository,
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     */
    #[Route(
        path: '/{requestWidth}{requestHeight}{regionOfInterestId}{quality}/{imageId}.jpg',
        name: 'get_one_file_name',
        requirements: [
            'imageId' => '[0-9a-zA-Z-]+',
            'requestWidth' => 'w\d+',
            'requestHeight' => '-h\d+',
            'regionOfInterestId' => '(-c\d+)|',
            'quality' => '(-q\d+)|',
        ],
        methods: ['GET']
    )]
    public function getOne(
        RequestedCropDto $cropPayload,
        string $imageId,
    ): Response {
        $image = $this->imageFileRepository->findProcessedById($imageId);

        if (null === $image) {
            return $this->createNotFoundResponse($cropPayload);
        }

        $roi = $this->roiRepository->findByImageIdAndPosition($imageId, $cropPayload->getRoi());
        if (null === $roi) {
            return $this->createNotFoundResponse($cropPayload);
        }

        return $this->okResponse(
            $this->cropFacade->applyCropPayload($image, $cropPayload, $roi),
            $image,
        );
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     * @throws NonUniqueResultException
     */
    private function createNotFoundResponse(RequestedCropDto $cropPayload): Response
    {
        $notFoundImageId = $this->configurationProvider->getSettings()->getNotFoundImageId();
        if (empty($notFoundImageId)) {
            throw new NotFoundHttpException('Image not found');
        }

        $notFoundImage = $this->imageFileRepository->findProcessedById($notFoundImageId);
        if (null === $notFoundImage) {
            throw new NotFoundHttpException('Image not found');
        }

        $notFoundRoi = $notFoundImage->getRegionsOfInterest()->first();
        if ($notFoundRoi instanceof RegionOfInterest) {
            return $this->notFoundResponse(
                $this->cropFacade->applyCropPayload($notFoundImage, $cropPayload, $notFoundRoi),
                $notFoundImage,
            );
        }

        throw new NotFoundHttpException('Image not found');
    }
}
