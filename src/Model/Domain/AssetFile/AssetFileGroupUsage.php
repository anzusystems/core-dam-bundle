<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;

/**
 * What an ext system says about one take-over group. Both answers are optional and mean different things:
 * no `used` is "could not ask", and no `holders` is "this ext system does not report them" — neither is
 * "nothing points at it", which is the only answer that may free a photo.
 */
final readonly class AssetFileGroupUsage
{
    /**
     * @param list<ImageHolderDto>|null $holders
     */
    public function __construct(
        private ?bool $used,
        private ?array $holders,
    ) {
    }

    public function isUsed(): ?bool
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
