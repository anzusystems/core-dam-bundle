<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\Collection;

#[AppAssert\CustomData]
#[AppAssert\Asset]
final class FormProvidableMetadataBulkUpdateDto implements AssetCustomFormProvidableInterface, CustomDataInterface
{
    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private Asset $asset;

    #[Serialize]
    private bool $described;

    #[Serialize]
    private bool $mainFileSingleUse;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData;

    #[Serialize(handler: EntityIdHandler::class, type: Keyword::class)]
    private Collection $keywords;

    #[Serialize(handler: EntityIdHandler::class, type: Author::class)]
    private Collection $authors;

    public function __construct()
    {
        $this->setAsset(new Asset());
    }

    public static function getInstance(Asset $asset): self
    {
        return (new self())
            ->setAsset($asset)
            ->setDescribed($asset->getAssetFlags()->isDescribed())
            ->setCustomData($asset->getMetadata()->getCustomData())
            ->setMainFileSingleUse((bool) $asset->getMainFile()?->getFlags()->isSingleUse())
            ->setAuthors($asset->getAuthors())
            ->setKeywords($asset->getKeywords())
        ;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function isCustomDataUndefined(): bool
    {
        return false === isset($this->customData);
    }

    public function setCustomData(array $customData): static
    {
        $this->customData = $customData;

        return $this;
    }

    public function isDescribed(): bool
    {
        return $this->described;
    }

    public function setDescribed(bool $described): self
    {
        $this->described = $described;

        return $this;
    }

    public function isDescribedUndefined(): bool
    {
        return false === isset($this->described);
    }

    /**
     * @return Collection<string, Keyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    /**
     * @param Collection<string, Keyword> $keywords
     */
    public function setKeywords(Collection $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function isKeywordsUndefined(): bool
    {
        return false === isset($this->keywords);
    }

    /**
     * @return Collection<string, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    /**
     * @param Collection<string, Author> $authors
     */
    public function setAuthors(Collection $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    public function isAuthorsUndefined(): bool
    {
        return false === isset($this->authors);
    }

    public function isMainFileSingleUse(): bool
    {
        return $this->mainFileSingleUse;
    }

    public function setMainFileSingleUse(bool $mainFileSingleUse): self
    {
        $this->mainFileSingleUse = $mainFileSingleUse;

        return $this;
    }

    public function isMainFileSingleUndefined(): bool
    {
        return false === isset($this->mainFileSingleUse);
    }

    public function getAssetType(): AssetType
    {
        return $this->getAsset()->getAttributes()->getAssetType();
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getAsset()->getExtSystem();
    }
}
