<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class ExtSystemTtsSettings
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $defaultVoiceFamilyId = null;

    #[ORM\Column(enumType: TtsActiveProviderMode::class, options: ['default' => 'auto'])]
    #[Serialize]
    private TtsActiveProviderMode $activeProviderMode = TtsActiveProviderMode::Default;

    public function getDefaultVoiceFamilyId(): ?string
    {
        return $this->defaultVoiceFamilyId;
    }

    public function setDefaultVoiceFamilyId(?string $defaultVoiceFamilyId): self
    {
        $this->defaultVoiceFamilyId = $defaultVoiceFamilyId;

        return $this;
    }

    public function getActiveProviderMode(): TtsActiveProviderMode
    {
        return $this->activeProviderMode;
    }

    public function setActiveProviderMode(TtsActiveProviderMode $activeProviderMode): self
    {
        $this->activeProviderMode = $activeProviderMode;

        return $this;
    }
}
