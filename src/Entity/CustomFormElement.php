<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\CustomFormElementAttributes;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\CustomFormElementRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomFormElementRepository::class)]
#[AppAssert\CustomFormElement]
#[ORM\Index(fields: ['attributes.searchable'], name: 'IDX_searchable')]
#[ORM\Index(fields: ['position'], name: 'IDX_position')]
class CustomFormElement implements TimeTrackingInterface, UuidIdentifiableInterface, UserTrackingInterface, PositionableInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use PositionTrait;

    /**
     * Form show name
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $name;

    /**
     * @deprecated
     */
    #[ORM\Column(name: 'key_name', type: Types::STRING, length: 255)]
    private string $key = '';

    /**
     * Json key name
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $property;

    #[ORM\Embedded(class: CustomFormElementAttributes::class)]
    #[Serialize]
    private CustomFormElementAttributes $attributes;

    #[ORM\ManyToOne(targetEntity: CustomForm::class, inversedBy: 'elements')]
    private CustomForm $form;

    /**
     * Defines autocomplete cascade
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $exifAutocomplete;

    public function __construct()
    {
        $this->setName('');
        $this->setProperty('');
        $this->setAttributes(new CustomFormElementAttributes());
        $this->setExifAutocomplete([]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function setProperty(string $property): self
    {
        $this->property = $property;

        return $this;
    }

    public function getAttributes(): CustomFormElementAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(CustomFormElementAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getForm(): CustomForm
    {
        return $this->form;
    }

    public function setForm(CustomForm $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function getExifAutocomplete(): array
    {
        return $this->exifAutocomplete;
    }

    public function setExifAutocomplete(array $exifAutocomplete): self
    {
        $this->exifAutocomplete = $exifAutocomplete;

        return $this;
    }
}
