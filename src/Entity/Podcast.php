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
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExportTypeEnableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ImagePreviewableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LinksHandler;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PodcastRepository::class)]
#[ORM\Index(name: 'IDX_name', fields: ['attributes.mode'])]
#[ORM\Index(name: 'IDX_licence_web_ordering', fields: ['attributes.webOrderPosition', 'licence', 'flags.webPublicExportEnabled'])]
#[ORM\Index(name: 'IDX_licence_mobile_ordering', fields: ['attributes.mobileOrderPosition', 'licence', 'flags.mobilePublicExportEnabled'])]
#[AppAssert\PodcastConstraint]
class Podcast implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    ExtSystemInterface,
    AssetLicenceInterface,
    ImagePreviewableInterface,
    ExportTypeEnableInterface
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

    #[ORM\OneToOne(targetEntity: ImagePreview::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Serialize]
    #[Assert\Valid]
    #[AppAssert\EqualLicence]
    protected ?ImagePreview $altImage;

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

    #[ORM\Embedded(class: PodcastFlags::class)]
    #[Serialize]
    #[Assert\Valid]
    private PodcastFlags $flags;

    #[ORM\OneToMany(targetEntity: PodcastEpisode::class, mappedBy: 'podcast')]
    private Collection $episodes;

    #[ORM\OneToMany(targetEntity: PodcastExportData::class, mappedBy: 'podcast')]
    #[Serialize(type: PodcastExportData::class)]
    private Collection $exportData;

    public function __construct()
    {
        $this->setTexts(new PodcastTexts());
        $this->setAttributes(new PodcastAttributes());
        $this->setEpisodes(new ArrayCollection());
        $this->setExportData(new ArrayCollection());
        $this->setImagePreview(null);
        $this->setAltImage(null);
        $this->setDates(new PodcastDates());
        $this->setFlags(new PodcastFlags());
    }

    public function getAltImage(): ?ImagePreview
    {
        return $this->altImage;
    }

    public function setAltImage(?ImagePreview $altImage): self
    {
        $this->altImage = $altImage;

        return $this;
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

    public function getExportData(): Collection
    {
        return $this->exportData;
    }

    public function setExportData(Collection $exportData): self
    {
        $this->exportData = $exportData;

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

    #[Serialize(handler: LinksHandler::class)]
    public function getLinks(): ?AssetFile
    {
        return $this->getImagePreview()?->getImageFile();
    }

    #[Serialize(handler: LinksHandler::class)]
    public function getAltLinks(): ?AssetFile
    {
        return $this->getAltImage()?->getImageFile();
    }

    public function getFlags(): PodcastFlags
    {
        return $this->flags;
    }

    public function setFlags(PodcastFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function isWebPublicExportEnabled(): bool
    {
        return $this->flags->isWebPublicExportEnabled();
    }

    public function isMobilePublicExportEnabled(): bool
    {
        return $this->flags->isMobilePublicExportEnabled();
    }
}
