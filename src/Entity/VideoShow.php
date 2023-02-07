<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\VideoShowRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoShowRepository::class)]
#[ORM\Index(fields: ['texts.title'], name: 'IDX_name')]
class VideoShow implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    ExtSystemInterface,
    AssetLicenceInterface
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

    #[ORM\OneToMany(mappedBy: 'videoShow', targetEntity: VideoShowEpisode::class)]
    private Collection $episodes;

    public function __construct()
    {
        $this->setTexts(new VideoShowTexts());
        $this->setEpisodes(new ArrayCollection());
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

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->licence->getExtSystem();
    }
}
