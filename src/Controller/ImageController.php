<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Cache\AssetFileCacheManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(path: '/image', name: 'image_')]
final class ImageController extends AbstractImageController
{
    private const int REDIRECT_TTL = 604_800;
    private const string REDIRECT_X_KEY = 'legacy_redirect';

    public function __construct(
        private readonly RegionOfInterestRepository $roiRepository,
        private readonly ImageUrlFactory $imageUrlFactory,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     * @throws NonUniqueResultException
     */
    #[Route(
        path: '/animated/{imageId}.gif',
        name: 'get_one_animated',
        requirements: [
            'imageId' => '[0-9a-zA-Z-]+',
        ],
        methods: [Request::METHOD_GET]
    )]
    public function animation(
        string $imageId,
    ): Response {
        $image = $this->imageFileRepository->findProcessedById($imageId);

        if (null === $image) {
            return $this->notFoundImageResponse(new RequestedCropDto());
        }

        if (false === $image->getImageAttributes()->isAnimated()) {
            return $this->notFoundImageResponse(new RequestedCropDto());
        }

        if (
            $this->domainProvider->isCurrentSchemeAndHostPublicDomain($image) &&
            false === $image->getFlags()->isPublic()
        ) {
            return $this->notFoundImageResponse(new RequestedCropDto());
        }

        return $this->streamResponse($image);
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
            'imageId' => Requirement::UUID,
            'requestWidth' => 'w\d+',
            'requestHeight' => '-h\d+',
            'regionOfInterestId' => '(-c\d+)|',
            'quality' => '(-q\d+)|',
        ],
        methods: [Request::METHOD_GET]
    )]
    public function getOne(
        RequestedCropDto $cropPayload,
        string $imageId,
    ): Response {
        // todo remove after blog production routes -c1 expires
        if ($cropPayload->getRoi() > App::ZERO) {
            return $this->redirectResponse($cropPayload, $imageId);
        }

        $image = $this->imageFileRepository->findProcessedById($imageId);

        if (null === $image) {
            return $this->notFoundImageResponse($cropPayload);
        }

        $roi = $this->roiRepository->findByImageIdAndPosition($imageId, $cropPayload->getRoi());
        if (null === $roi) {
            return $this->notFoundImageResponse($cropPayload);
        }

        if (
            $this->domainProvider->isCurrentSchemeAndHostPublicDomain($image) &&
            false === $image->getFlags()->isPublic()
        ) {
            return $this->notFoundImageResponse($cropPayload);
        }

        return $this->okImageResponse(
            image: $image,
            roi: $roi,
            cropPayload: $cropPayload
        );
    }

    private function redirectResponse(
        RequestedCropDto $cropPayload,
        string $imageId,
    ): RedirectResponse {
        $response = new RedirectResponse(
            url: $this->imageUrlFactory->generatePublicUrl(
                imageId: $imageId,
                width: $cropPayload->getRequestWidth(),
                height: $cropPayload->getRequestHeight(),
                roiPosition: App::ZERO,
                quality: $cropPayload->getQuality()
            ),
            status: Response::HTTP_MOVED_PERMANENTLY,
        );
        $response->setPublic();
        $response->setMaxAge(App::ZERO);
        $response->headers->set(AssetFileCacheManager::CACHE_CONTROL_TTL_HEADER, (string) self::REDIRECT_TTL);

        $response->headers->set(AssetFileCacheManager::X_KEY_HEADER, implode(' ', [
            AssetFileCacheManager::getSystemXkey(),
            $imageId,
            self::REDIRECT_X_KEY,
        ]));

        return $response;
    }
}
