<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

final class ExtSystemTtsSettingsUpdateDto
{
    #[Serialize]
    private ?string $defaultVoiceFamilyId = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetLicence $ttsDefaultAssetLicence = null;

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

    public function getTtsDefaultAssetLicence(): ?AssetLicence
    {
        return $this->ttsDefaultAssetLicence;
    }

    public function setTtsDefaultAssetLicence(?AssetLicence $ttsDefaultAssetLicence): self
    {
        $this->ttsDefaultAssetLicence = $ttsDefaultAssetLicence;

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
