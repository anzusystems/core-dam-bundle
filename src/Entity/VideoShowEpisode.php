<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VideoShowEpisodeRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoShowEpisodeRepository::class)]
#[ORM\Index(fields: ['videoShow', 'position'], name: 'IDX_video_show_position')]
#[ORM\Index(fields: ['position'], name: 'IDX_position')]
class VideoShowEpisode implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    PositionableInterface,
    ExtSystemInterface,
    AssetLicenceInterface
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

    public function __construct()
    {
        $this->setTexts(new VideoShowEpisodeTexts());
        $this->setAsset(null);
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

    public function getLicence(): AssetLicence
    {
        return $this->getVideoShow()->getLicence();
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getVideoShow()->getLicence()->getExtSystem();
    }
}
