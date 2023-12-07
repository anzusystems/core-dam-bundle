<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Cache\AssetFileCacheManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/image', name: 'image_')]
final class ImageController extends AbstractImageController
{
    private const REDIRECT_TTL = 604_800;
    private const REDIRECT_X_KEY = 'legacy_redirect';

    public function __construct(
        private readonly RegionOfInterestRepository $roiRepository,
        private readonly ImageUrlFactory $imageUrlFactory,
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
            false === $image->getFlags()->isPublic() &&
            $this->domainProvider->isCurrentSchemeAndHostPublicDomain($image)
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
