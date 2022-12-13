<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\NotifyToTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset implements
    TimeTrackingInterface,
    UuidIdentifiableInterface,
    UserTrackingInterface,
    ExtSystemIndexableInterface,
    NotifiableInterface,
    AssetCustomFormProvidableInterface,
    AssetLicenceInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use NotifyToTrait;

    #[ORM\ManyToMany(targetEntity: Author::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable]
    private Collection $authors;

    #[ORM\ManyToMany(targetEntity: Keyword::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable]
    private Collection $keywords;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: PodcastEpisode::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $episodes;

    #[ORM\Embedded(class: AssetTexts::class)]
    private AssetTexts $texts;

    #[ORM\Embedded(class: AssetDates::class)]
    private AssetDates $dates;

    #[ORM\Embedded(class: AssetFlags::class)]
    private AssetFlags $assetFlags;

    #[ORM\Embedded(class: AssetAttributes::class)]
    private AssetAttributes $attributes;

    #[ORM\OneToOne(targetEntity: AssetMetadata::class)]
    private AssetMetadata $metadata;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private AssetLicence $licence;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetHasFile::class)]
    private Collection $files;

    #[ORM\ManyToOne(targetEntity: DistributionCategory::class)]
    private ?DistributionCategory $distributionCategory;

    public function __construct()
    {
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
        $this->setAttributes(new AssetAttributes());
        $this->setTexts(new AssetTexts());
        $this->setAssetFlags(new AssetFlags());
        $this->setDates(new AssetDates());
        $this->setFiles(new ArrayCollection());
        $this->setAuthors(new ArrayCollection());
        $this->setKeywords(new ArrayCollection());
        $this->setDistributionCategory(null);
        $this->setEpisodes(new ArrayCollection());
    }

    public function getTexts(): AssetTexts
    {
        return $this->texts;
    }

    public function setTexts(AssetTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getDates(): AssetDates
    {
        return $this->dates;
    }

    public function setDates(AssetDates $dates): self
    {
        $this->dates = $dates;

        return $this;
    }

    public function getAssetFlags(): AssetFlags
    {
        return $this->assetFlags;
    }

    public function setAssetFlags(AssetFlags $assetFlags): self
    {
        $this->assetFlags = $assetFlags;

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

    public function getAttributes(): AssetAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(AssetAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return Collection<int, AssetHasFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function setFiles(Collection $files): self
    {
        $this->files = $files;

        return $this;
    }

    public function getMetadata(): AssetMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(AssetMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function setAuthors(Collection $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    public function setKeywords(Collection $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getDistributionCategory(): ?DistributionCategory
    {
        return $this->distributionCategory;
    }

    public function setDistributionCategory(?DistributionCategory $distributionCategory): self
    {
        $this->distributionCategory = $distributionCategory;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getLicence()->getExtSystem();
    }

    public function getAssetType(): AssetType
    {
        return $this->getAttributes()->getAssetType();
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
}
