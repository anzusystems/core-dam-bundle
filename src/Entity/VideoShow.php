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
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExportTypeEnableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\VideoShowRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoShowRepository::class)]
#[ORM\Index(name: 'IDX_name', fields: ['texts.title'])]
#[ORM\Index(name: 'IDX_licence_web_ordering', fields: ['licence', 'attributes.webOrderPosition', 'flags.webPublicExportEnabled'])]
#[ORM\Index(name: 'IDX_licence_mobile_ordering', fields: ['licence', 'attributes.mobileOrderPosition', 'flags.mobilePublicExportEnabled'])]
class VideoShow implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    ExtSystemInterface,
    AssetLicenceInterface,
    ExportTypeEnableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected AssetLicence $licence;

    #[ORM\Embedded(class: VideoShowTexts::class)]
    #[Serialize]
    #[Assert\Valid]
    private VideoShowTexts $texts;

    #[ORM\Embedded(class: VideoShowFlags::class)]
    #[Serialize]
    #[Assert\Valid]
    private VideoShowFlags $flags;

    #[ORM\Embedded(class: VideoShowAttributes::class)]
    #[Serialize]
    #[Assert\Valid]
    private VideoShowAttributes $attributes;

    #[ORM\OneToMany(targetEntity: VideoShowEpisode::class, mappedBy: 'videoShow')]
    private Collection $episodes;

    public function __construct()
    {
        $this->setTexts(new VideoShowTexts());
        $this->setEpisodes(new ArrayCollection());
        $this->setFlags(new VideoShowFlags());
        $this->setAttributes(new VideoShowAttributes());
    }

    public function getTexts(): VideoShowTexts
    {
        return $this->texts;
    }

    public function setTexts(VideoShowTexts $texts): self
    {
        $this->texts = $texts;

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

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    public function getFlags(): VideoShowFlags
    {
        return $this->flags;
    }

    public function setFlags(VideoShowFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getAttributes(): VideoShowAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(VideoShowAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->licence->getExtSystem();
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
