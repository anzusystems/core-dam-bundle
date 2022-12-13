<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use Symfony\Component\Routing\RouterInterface;

final class ImageUrlFactory
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
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
                'regionOfInterestId' => $roiPosition ? "-c{$roiPosition}" : '',
                'requestWidth' => "w{$width}",
                'requestHeight' => "-h{$height}",
                'quality' => $quality ? "-q{$quality}" : '',
            ]
        );
    }
}
