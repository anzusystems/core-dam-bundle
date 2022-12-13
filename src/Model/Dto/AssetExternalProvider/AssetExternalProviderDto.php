<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider;

use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds\AssetExternalProviderAttributesDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds\AssetExternalProviderTextsDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetExternalProviderDto
{
    private string $id;
    private string $url;
    private AssetExternalProviderAttributesDto $attributes;
    private AssetExternalProviderTextsDto $texts;
    private AssetExternalProviderMetadataDto $metadata;

    public static function getInstance(
        string $id,
        string $url,
        AssetExternalProviderAttributesDto $attributes,
        AssetExternalProviderTextsDto $texts,
        AssetExternalProviderMetadataDto $metadata,
    ): self {
        return (new self())
            ->setId($id)
            ->setUrl($url)
            ->setAttributes($attributes)
            ->setTexts($texts)
            ->setMetadata($metadata)
        ;
    }

    #[Serialize]
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    #[Serialize]
    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    #[Serialize]
    public function getAttributes(): AssetExternalProviderAttributesDto
    {
        return $this->attributes;
    }

    public function setAttributes(AssetExternalProviderAttributesDto $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    #[Serialize]
    public function getTexts(): AssetExternalProviderTextsDto
    {
        return $this->texts;
    }

    public function setTexts(AssetExternalProviderTextsDto $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    #[Serialize]
    public function getMetadata(): AssetExternalProviderMetadataDto
    {
        return $this->metadata;
    }

    public function setMetadata(AssetExternalProviderMetadataDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
