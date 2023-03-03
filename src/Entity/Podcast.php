<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ImagePreviewableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PodcastRepository::class)]
#[ORM\Index(fields: ['attributes.mode'], name: 'IDX_name')]
class Podcast implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    ExtSystemInterface,
    AssetLicenceInterface,
    ImagePreviewableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected AssetLicence $licence;

    #[ORM\OneToOne(targetEntity: ImagePreview::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Serialize]
    #[Assert\Valid]
    #[AppAssert\EqualLicence]
    protected ?ImagePreview $imagePreview;

    #[ORM\Embedded(class: PodcastTexts::class)]
    #[Serialize]
    #[Assert\Valid]
    private PodcastTexts $texts;

    #[ORM\Embedded(class: PodcastDates::class)]
    #[Serialize]
    #[Assert\Valid]
    private PodcastDates $dates;

    #[ORM\Embedded(class: PodcastAttributes::class)]
    #[Serialize]
    #[Assert\Valid]
    private PodcastAttributes $attributes;

    #[ORM\OneToMany(mappedBy: 'podcast', targetEntity: PodcastEpisode::class)]
    private Collection $episodes;

    public function __construct()
    {
        $this->setTexts(new PodcastTexts());
        $this->setAttributes(new PodcastAttributes());
        $this->setEpisodes(new ArrayCollection());
        $this->setImagePreview(null);
        $this->setDates(new PodcastDates());
    }

    public function getImagePreview(): ?ImagePreview
    {
        return $this->imagePreview;
    }

    public function setImagePreview(?ImagePreview $imagePreview): self
    {
        $this->imagePreview = $imagePreview;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    public function getTexts(): PodcastTexts
    {
        return $this->texts;
    }

    public function setTexts(PodcastTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getAttributes(): PodcastAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(PodcastAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function setEpisodes(Collection $episodes): self
    {
        $this->episodes = $episodes;

        return $this;
    }

    public function getDates(): PodcastDates
    {
        return $this->dates;
    }

    public function setDates(PodcastDates $dates): self
    {
        $this->dates = $dates;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->licence->getExtSystem();
    }

    #[Serialize(handler: ImageLinksHandler::class, type: ImageCropTag::LIST)]
    public function getLinks(): ?AssetFile
    {
        return $this->getImagePreview()?->getImageFile();
    }
}
