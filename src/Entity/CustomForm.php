<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\CustomFormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomFormRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
class CustomForm implements TimeTrackingInterface, UuidIdentifiableInterface, UserTrackingInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;

    #[ORM\OneToMany(mappedBy: 'form', targetEntity: CustomFormElement::class)]
    #[ORM\OrderBy(value: ['position' => App::ORDER_ASC])]
    #[Assert\Valid]
    private Collection $elements;

    public function __construct()
    {
        $this->setElements(new ArrayCollection());
    }

    /**
     * @return Collection<int, CustomFormElement>
     */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, CustomFormElement> $elements
     */
    public function setElements(Collection $elements): self
    {
        $this->elements = $elements;

        return $this;
    }
}
