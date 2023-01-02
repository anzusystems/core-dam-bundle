<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PodcastEpisodeRepository::class)]
#[ORM\Index(fields: ['podcast', 'position'], name: 'IDX_podcast_position')]
#[ORM\Index(fields: ['position'], name: 'IDX_position')]
#[ORM\Index(fields: ['texts.title', 'licenceId'], name: 'IDX_title_licence_id')]
class PodcastEpisode implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    PositionableInterface,
    ExtSystemInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;
    use PositionTrait;

    #[ORM\ManyToOne(targetEntity: Podcast::class, inversedBy: 'episodes')]
    #[Serialize(handler: EntityIdHandler::class)]
    private Podcast $podcast;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'episodes')]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?Asset $asset;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private ?Asset $previewImage;

    #[Serialize]
    #[ORM\Embedded(class: PodcastEpisodeDates::class)]
    #[Assert\Valid]
    private PodcastEpisodeDates $dates;

    #[Serialize]
    #[ORM\Embedded(class: PodcastEpisodeAttributes::class)]
    #[Assert\Valid]
    private PodcastEpisodeAttributes $attributes;

    #[Serialize]
    #[ORM\Embedded(class: PodcastEpisodeTexts::class)]
    #[Assert\Valid]
    private PodcastEpisodeTexts $texts;

    public function __construct()
    {
        $this->setDates(new PodcastEpisodeDates());
        $this->setAttributes(new PodcastEpisodeAttributes());
        $this->setTexts(new PodcastEpisodeTexts());
        $this->setAsset(null);
        $this->setPreviewImage(null);
    }

    public function getPreviewImage(): ?Asset
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?Asset $previewImage): self
    {
        $this->previewImage = $previewImage;
        return $this;
    }

    public function getPodcast(): Podcast
    {
        return $this->podcast;
    }

    public function setPodcast(Podcast $podcast): self
    {
        $this->podcast = $podcast;

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

    public function getDates(): PodcastEpisodeDates
    {
        return $this->dates;
    }

    public function setDates(PodcastEpisodeDates $dates): self
    {
        $this->dates = $dates;

        return $this;
    }

    public function getAttributes(): PodcastEpisodeAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(PodcastEpisodeAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getTexts(): PodcastEpisodeTexts
    {
        return $this->texts;
    }

    public function setTexts(PodcastEpisodeTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getPodcast()->getLicence()->getExtSystem();
    }
}
