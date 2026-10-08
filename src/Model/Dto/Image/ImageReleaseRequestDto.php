<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Drops the given holder's claim on a whole batch of photos in one call — each take-over group is released
 * only where this holder actually holds it, every other one is silently skipped
 * ({@see ImageReleaseFacade::releaseImages()}).
 */
final class ImageReleaseRequestDto
{
    public const int MAX_ITEMS = 200;

    #[Serialize]
    #[Assert\Valid]
    private ImageHolderDto $holder;

    #[Serialize(handler: EntityIdHandler::class, type: ImageFile::class)]
    #[Assert\Count(
        max: self::MAX_ITEMS,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private Collection $imageFileIds;

    public function __construct()
    {
        $this->setHolder(new ImageHolderDto());
        $this->setImageFileIds(new ArrayCollection());
    }

    public function getHolder(): ImageHolderDto
    {
        return $this->holder;
    }

    public function setHolder(ImageHolderDto $holder): self
    {
        $this->holder = $holder;

        return $this;
    }

    /**
     * @return Collection<array-key, ImageFile>
     */
    public function getImageFileIds(): Collection
    {
        return $this->imageFileIds;
    }

    /**
     * @param Collection<array-key, ImageFile> $imageFileIds
     */
    public function setImageFileIds(Collection $imageFileIds): self
    {
        $this->imageFileIds = $imageFileIds;

        return $this;
    }
}
