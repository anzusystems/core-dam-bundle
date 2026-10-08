<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;

/**
 * What an ext system answers about one image file: whether it still points at it, and — when it reports
 * them at all — who holds it there. `null` holders mean the ext system does not report holders, not that
 * nobody holds the photo.
 */
final readonly class ImageFileUsage
{
    /**
     * @param list<ImageHolderDto>|null $holders
     */
    public function __construct(
        private bool $used,
        private ?array $holders = null,
    ) {
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    /**
     * @return list<ImageHolderDto>|null
     */
    public function getHolders(): ?array
    {
        return $this->holders;
    }
}
