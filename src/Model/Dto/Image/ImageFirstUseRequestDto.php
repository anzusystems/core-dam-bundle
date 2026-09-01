<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

final class ImageFirstUseRequestDto
{
    public const int MAX_ITEMS = 200;

    #[Assert\Valid]
    #[Serialize(type: ImageFirstUseItemDto::class)]
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

    /**
     * @return Collection<array-key, ImageFirstUseItemDto>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setItems(Collection $items): void
    {
        $this->items = $items;
    }
}
