<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\ImagePreview;

interface ImagePreviewableInterface
{
    public function getImagePreview(): ?ImagePreview;

    public function setImagePreview(?ImagePreview $imagePreview): self;
}
