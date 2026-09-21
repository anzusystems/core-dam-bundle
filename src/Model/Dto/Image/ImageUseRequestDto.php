<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One request claims a whole batch of photos for the same holder in one call — a gallery of 20 images no
 * longer costs 20 round trips. {@see ImageUseFacade::useImages()} resolves and, where single use, claims
 * every item under one lock, all-or-nothing.
 */
final class ImageUseRequestDto
{
    public const int MAX_ITEMS = 200;

    /**
     * The caller claiming the images, written onto the whole take-over group of every single use item
     * ({@see ImageUseFacade}). Null when the caller has nothing that may hold a photo — the batch is then
     * resolved and recorded as used, but nothing is claimed, and a single use item is refused.
     */
    #[Serialize]
    #[Assert\Valid]
    private ?ImageHolderDto $holder = null;

    /**
     * A holder handing the photos over to {@see $holder}: a group it currently holds counts as free instead
     * of as a conflict, and the claim overwrites it under the same lock. Without it a hand over has to
     * release first and claim second, and between the two calls anybody can take the photo. Ignored when
     * there is no holder to hand over to.
     */
    #[Serialize]
    #[Assert\Valid]
    private ?ImageHolderDto $releaseFrom = null;

    #[Serialize(type: ImageUseItemDto::class)]
    #[Assert\Valid]
    #[Assert\Count(
        min: 1,
        max: self::MAX_ITEMS,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private Collection $items;

    public function __construct()
    {
        $this->setItems(new ArrayCollection());
    }

    public function getHolder(): ?ImageHolderDto
    {
        return $this->holder;
    }

    public function setHolder(?ImageHolderDto $holder): self
    {
        $this->holder = $holder;

        return $this;
    }

    public function getReleaseFrom(): ?ImageHolderDto
    {
        return $this->releaseFrom;
    }

    public function setReleaseFrom(?ImageHolderDto $releaseFrom): self
    {
        $this->releaseFrom = $releaseFrom;

        return $this;
    }

    /**
     * @return Collection<array-key, ImageUseItemDto>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param Collection<array-key, ImageUseItemDto> $items
     */
    public function setItems(Collection $items): self
    {
        $this->items = $items;

        return $this;
    }
}
