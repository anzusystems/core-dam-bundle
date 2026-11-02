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
 * Moves photos the caller already uses from one of its holders to another, in one call
 * ({@see \AnzuSystems\CoreDamBundle\Domain\Image\ImageTransferFacade::transferImages()}).
 */
final class ImageTransferRequestDto
{
    public const int MAX_ITEMS = 1_000;

    #[Serialize]
    #[Assert\Valid]
    private ImageHolderDto $from;

    #[Serialize]
    #[Assert\Valid]
    private ImageHolderDto $to;

    #[Serialize(handler: EntityIdHandler::class, type: ImageFile::class)]
    #[Assert\Count(
        max: self::MAX_ITEMS,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private Collection $imageFileIds;

    public function __construct()
    {
        $this->setFrom(new ImageHolderDto());
        $this->setTo(new ImageHolderDto());
        $this->setImageFileIds(new ArrayCollection());
    }

    public function getFrom(): ImageHolderDto
    {
        return $this->from;
    }

    public function setFrom(ImageHolderDto $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): ImageHolderDto
    {
        return $this->to;
    }

    public function setTo(ImageHolderDto $to): self
    {
        $this->to = $to;

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
