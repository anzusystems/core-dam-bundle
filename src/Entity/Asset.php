<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\CopyableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory\AssetDBALIndexFactory;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileProperties;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetTexts;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\DBALIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\NotifyToTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetDBALRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @psalm-method DamUser getCreatedBy()
 * @psalm-method DamUser getModifiedBy()
 */
#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Index(name: 'IDX_status_created_auto_delete', fields: ['attributes.status', 'createdAt', 'assetFlags.autoDeleteUnprocessed'])]
#[ORM\Index(name: 'IDX_modified_at', fields: ['modifiedAt'])]
class Asset implements
    TimeTrackingInterface,
    UuidIdentifiableInterface,
    UserTrackingInterface,
    ExtSystemIndexableInterface,
    NotifiableInterface,
    AssetCustomFormProvidableInterface,
    AssetLicenceInterface,
    CopyableInterface,
    DBALIndexableInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use NotifyToTrait;

    #[ORM\ManyToMany(targetEntity: Author::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    private Collection $authors;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?Asset $siblingToAsset;

    #[ORM\OneToOne(targetEntity: AssetFile::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetFile $mainFile;

    #[ORM\ManyToMany(targetEntity: Keyword::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    private Collection $keywords;

    #[ORM\OneToMany(targetEntity: PodcastEpisode::class, mappedBy: 'asset', fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $episodes;

    #[ORM\OneToMany(targetEntity: VideoShowEpisode::class, mappedBy: 'asset', fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $videoEpisodes;

    #[ORM\Embedded(class: AssetTexts::class)]
    private AssetTexts $texts;

    #[ORM\Embedded(class: AssetFileProperties::class)]
    private AssetFileProperties $assetFileProperties;

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

    // todo not nullable after ExtSystemsMigration
    #[ORM\ManyToOne(targetEntity: ExtSystem::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private ?ExtSystem $extSystem;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetSlot::class)]
    private Collection $slots;

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
        $this->setSlots(new ArrayCollection());
        $this->setAuthors(new ArrayCollection());
        $this->setKeywords(new ArrayCollection());
        $this->setDistributionCategory(null);
        $this->setEpisodes(new ArrayCollection());
        $this->setVideoEpisodes(new ArrayCollection());
        $this->setMainFile(null);
        $this->setAssetFileProperties(new AssetFileProperties());
        $this->setExtSystem(null);
        $this->setSiblingToAsset(null);
    }

    public function __copy(): self
    {
        return (new self())
            ->setAttributes(clone $this->getAttributes())
            ->setTexts(clone $this->getTexts())
            ->setAssetFlags(clone $this->getAssetFlags())
            ->setAssetFileProperties(clone $this->getAssetFileProperties())
            ->setMetadata($this->getMetadata()->__copy())
            ->setAuthors($this->getAuthors())
            ->setKeywords($this->getKeywords())
            ->setDistributionCategory($this->getDistributionCategory())
            ->setMainFile(null)

            ->setExtSystem($this->getExtSystem())
        ;
    }

    public function getMainFile(): ?AssetFile
    {
        return $this->mainFile;
    }

    public function setMainFile(?AssetFile $mainFile): self
    {
        $this->mainFile = $mainFile;

        return $this;
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

    public function addSlot(AssetSlot $slot): self
    {
        $this->slots->add($slot);
        $slot->setAsset($this);

        return $this;
    }

    public function removeSlot(AssetSlot $slot): self
    {
        $this->slots->removeElement($slot);

        return $this;
    }

    /**
     * @return Collection<int, AssetSlot>
     */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function setSlots(Collection $slots): self
    {
        $this->slots = $slots;

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

    /**
     * @return Collection<string, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): self
    {
        if (false === $this->authors->contains($author)) {
            $this->authors->add($author);
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAuthorsAsStringArray(): array
    {
        return $this->getAuthors()->map(
            fn (Author $author): string => $author->getName()
        )->getValues();
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, Author> $authors
     */
    public function setAuthors(Collection $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    /**
     * @return Collection<string, Keyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    public function addKeyword(Keyword $keyword): self
    {
        if (false === $this->keywords->contains($keyword)) {
            $this->keywords->add($keyword);
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getKeywordsAsStringArray(): array
    {
        return $this->getKeywords()->map(
            fn (Keyword $keyword): string => $keyword->getName()
        )->getValues();
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, Keyword> $keywords
     */
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

    public function setExtSystem(?ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        // todo remove after ExtSystemsMigration

        return $this->extSystem ?? $this->getLicence()->getExtSystem();
    }

    public function getAssetType(): AssetType
    {
        return $this->getAttributes()->getAssetType();
    }

    public function addEpisode(PodcastEpisode $episode): self
    {
        $this->episodes->add($episode);
        $episode->setAsset($this);

        return $this;
    }

    /**
     * @return Collection<int, PodcastEpisode>
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function setEpisodes(Collection $episodes): self
    {
        $this->episodes = $episodes;

        return $this;
    }

    public function addVideoEpisode(VideoShowEpisode $episode): self
    {
        $this->videoEpisodes->add($episode);
        $episode->setAsset($this);

        return $this;
    }

    /**
     * @return Collection<int, VideoShowEpisode>
     */
    public function getVideoEpisodes(): Collection
    {
        return $this->videoEpisodes;
    }

    public function setVideoEpisodes(Collection $videoEpisodes): self
    {
        $this->videoEpisodes = $videoEpisodes;

        return $this;
    }

    public function getAssetFileProperties(): AssetFileProperties
    {
        return $this->assetFileProperties;
    }

    public function setAssetFileProperties(AssetFileProperties $assetFileProperties): self
    {
        $this->assetFileProperties = $assetFileProperties;

        return $this;
    }

    public static function getIndexName(): string
    {
        return self::getResourceName();
    }

    public function hasSameFilesIdentityString(self $asset): bool
    {
        return $asset->getFilesIdentityString() === $this->getFilesIdentityString();
    }

    public function getFilesIdentityString(): string
    {
        $identityParts = [];
        foreach ($this->getSlots() as $slot) {
            $identityParts[$slot->getName()] =
                $slot->getName() . ':' . $slot->getAssetFile()->getAssetAttributes()->getChecksum();
        }
        ksort($identityParts);

        return implode('_', $identityParts);
    }

    public function getSiblingToAsset(): ?self
    {
        return $this->siblingToAsset;
    }

    public function setSiblingToAsset(?self $siblingToAsset): self
    {
        $this->siblingToAsset = $siblingToAsset;

        return $this;
    }

    public static function getDBALIndexFactoryClassName(): string
    {
        return AssetDBALIndexFactory::class;
    }

    public static function getRepositoryClassName(): string
    {
        return AssetDBALRepository::class;
    }
}
