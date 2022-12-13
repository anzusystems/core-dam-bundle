<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata;

use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetMetadataAdmDetailDto
{
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $keywordSuggestions;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $authorSuggestions;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData;

    public static function getInstance(AssetMetadata $assetMetadata): self
    {
        return (new self())
            ->setKeywordSuggestions($assetMetadata->getKeywordSuggestions())
            ->setAuthorSuggestions($assetMetadata->getAuthorSuggestions())
            ->setCustomData($assetMetadata->getCustomData())
        ;
    }

    public function getKeywordSuggestions(): array
    {
        return $this->keywordSuggestions;
    }

    public function setKeywordSuggestions(array $keywordSuggestions): self
    {
        $this->keywordSuggestions = $keywordSuggestions;

        return $this;
    }

    public function getAuthorSuggestions(): array
    {
        return $this->authorSuggestions;
    }

    public function setAuthorSuggestions(array $authorSuggestions): self
    {
        $this->authorSuggestions = $authorSuggestions;

        return $this;
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function setCustomData(array $customData): self
    {
        $this->customData = $customData;

        return $this;
    }
}
