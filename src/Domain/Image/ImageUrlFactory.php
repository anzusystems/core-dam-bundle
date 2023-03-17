<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use Symfony\Component\Routing\RouterInterface;

final readonly class ImageUrlFactory
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function generateAllowListUrl(
        string $imageId,
        CropAllowItem $item,
        ?int $roiPosition = null,
        ?int $quality = null,
    ): string {
        return $this->generatePublicUrl(
            imageId: $imageId,
            width: $item->getWidth(),
            height: $item->getHeight(),
            roiPosition: $roiPosition,
            quality: $quality
        );
    }

    public function generatePublicUrl(
        string $imageId,
        int $width,
        int $height,
        ?int $roiPosition = null,
        ?int $quality = null,
    ): string {
        return $this->router->generate(
            'image_get_one_file_name',
            [
                'imageId' => $imageId,
                'regionOfInterestId' => null === $roiPosition ? '' : "-c{$roiPosition}",
                'requestWidth' => "w{$width}",
                'requestHeight' => "-h{$height}",
                'quality' => null === $quality ? '' : "-q{$quality}",
            ]
        );
    }
}
