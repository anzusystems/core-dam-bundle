<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;

final class OptimalResizeManager extends AbstractManager
{
    public function __construct(
        private readonly FileStash $stash,
    ) {
    }

    public function create(ImageFileOptimalResize $resize, bool $flush = true): ImageFileOptimalResize
    {
        $this->trackCreation($resize);
        $this->entityManager->persist($resize);
        $this->flush($flush);

        return $resize;
    }

    public function deleteByImage(ImageFile $assetFile): void
    {
        foreach ($assetFile->getResizes() as $resize) {
            $this->stash->add($resize);
            $this->entityManager->remove($resize);
        }
    }
}
