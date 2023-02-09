<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\CustomFormRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomFormRepository::class)]
class AssetMetadata implements TimeTrackingInterface, UuidIdentifiableInterface, UserTrackingInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $keywordSuggestions;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $authorSuggestions;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData;

    public function __construct()
    {
        $this->setCustomData([]);
        $this->setKeywordSuggestions([]);
        $this->setAuthorSuggestions([]);
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function addCustomDataValue(string $key, mixed $value): self
    {
        $this->customData[$key] = $value;

        return $this;
    }

    public function setCustomData(array $customData): self
    {
        $this->customData = $customData;

        return $this;
    }

    /**
     * @return array<string, string[]>
     */
    public function getKeywordSuggestions(): array
    {
        return $this->keywordSuggestions;
    }

    public function setKeywordSuggestions(array $keywordSuggestions): self
    {
        $this->keywordSuggestions = $keywordSuggestions;

        return $this;
    }

    /**
     * @return array<string, string[]>
     */
    public function getAuthorSuggestions(): array
    {
        return $this->authorSuggestions;
    }

    public function setAuthorSuggestions(array $authorSuggestions): self
    {
        $this->authorSuggestions = $authorSuggestions;

        return $this;
    }
}
