<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExportTypeEnableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VideoShowEpisodeRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoShowEpisodeRepository::class)]
#[ORM\Index(name: 'IDX_video_show_position', fields: ['videoShow', 'position'])]
#[ORM\Index(name: 'IDX_position', fields: ['position'])]
#[ORM\Index(name: 'IDX_show_publication_mobile_ordering', fields: ['attributes.mobileOrderPosition', 'asset', 'videoShow', 'flags.mobilePublicExportEnabled', 'dates.publicationDate'])]
#[ORM\Index(name: 'IDX_show_publication_web_ordering', fields: ['attributes.webOrderPosition', 'asset', 'videoShow', 'flags.webPublicExportEnabled', 'dates.publicationDate'])]
class VideoShowEpisode implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    PositionableInterface,
    ExtSystemInterface,
    AssetLicenceInterface,
    ExportTypeEnableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;
    use PositionTrait;

    #[ORM\ManyToOne(targetEntity: VideoShow::class, inversedBy: 'episodes')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotNull(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[AppAssert\EqualLicence]
    private VideoShow $videoShow;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'videoEpisodes')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[AppAssert\AssetProperties(assetType: AssetType::Video)]
    #[AppAssert\EqualLicence]
    private ?Asset $asset;

    #[Serialize]
    #[ORM\Embedded(class: VideoShowEpisodeTexts::class)]
    #[Assert\Valid]
    private VideoShowEpisodeTexts $texts;

    #[Serialize]
    #[ORM\Embedded(class: VideoShowEpisodeDates::class)]
    #[Assert\Valid]
    private VideoShowEpisodeDates $dates;

    #[ORM\Embedded(class: VideoShowEpisodeFlags::class)]
    #[Serialize]
    #[Assert\Valid]
    private VideoShowEpisodeFlags $flags;

    #[ORM\Embedded(class: VideoShowEpisodeAttributes::class)]
    #[Serialize]
    #[Assert\Valid]
    private VideoShowEpisodeAttributes $attributes;

    public function __construct()
    {
        $this->setTexts(new VideoShowEpisodeTexts());
        $this->setAsset(null);
        $this->setDates(new VideoShowEpisodeDates());
        $this->setFlags(new VideoShowEpisodeFlags());
        $this->setAttributes(new VideoShowEpisodeAttributes());
    }

    public function getVideoShow(): VideoShow
    {
        return $this->videoShow;
    }

    public function setVideoShow(VideoShow $videoShow): self
    {
        $this->videoShow = $videoShow;

        return $this;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function getTexts(): VideoShowEpisodeTexts
    {
        return $this->texts;
    }

    public function setTexts(VideoShowEpisodeTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getDates(): VideoShowEpisodeDates
    {
        return $this->dates;
    }

    public function setDates(VideoShowEpisodeDates $dates): self
    {
        $this->dates = $dates;

        return $this;
    }

    public function getFlags(): VideoShowEpisodeFlags
    {
        return $this->flags;
    }

    public function setFlags(VideoShowEpisodeFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getAttributes(): VideoShowEpisodeAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(VideoShowEpisodeAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->getVideoShow()->getLicence();
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getVideoShow()->getLicence()->getExtSystem();
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
