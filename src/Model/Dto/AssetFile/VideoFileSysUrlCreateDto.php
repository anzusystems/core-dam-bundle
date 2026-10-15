<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\VideoFileSysUrlCreate]
final class VideoFileSysUrlCreateDto extends AbstractAssetFileSysDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 2_048, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\Url(message: ValidationException::ERROR_FIELD_INVALID)]
    private string $url = '';

    #[Serialize]
    private array $authors = [];

    #[Serialize]
    private array $keywords = [];

    /**
     * @var list<string>
     */
    #[Assert\Unique(message: ValidationException::ERROR_FIELD_UNIQUE)]
    #[Serialize]
    private array $distributionServices = [];

    #[Serialize]
    private ?string $imagePreviewAssetFileId = null;

    public function getAssetType(): AssetType
    {
        return AssetType::Video;
    }

    public function isGeneratePublicRoute(): bool
    {
        return false;
    }

    public function hasLicence(): bool
    {
        return isset($this->licence);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function setAuthors(array $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDistributionServices(): array
    {
        return $this->distributionServices;
    }

    /**
     * @param list<string> $distributionServices
     */
    public function setDistributionServices(array $distributionServices): self
    {
        $this->distributionServices = $distributionServices;

        return $this;
    }

    public function getImagePreviewAssetFileId(): ?string
    {
        return $this->imagePreviewAssetFileId;
    }

    public function setImagePreviewAssetFileId(?string $imagePreviewAssetFileId): self
    {
        $this->imagePreviewAssetFileId = $imagePreviewAssetFileId;

        return $this;
    }
}
