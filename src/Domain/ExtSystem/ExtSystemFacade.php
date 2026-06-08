<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;

final class ExtSystemFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly ExtSystemManager $extSystemManager,
        private readonly VoiceFamilyRepository $voiceFamilyRepository,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(ExtSystem $extSystem, ExtSystem $newExtSystem): ExtSystem
    {
        $this->validator->validate($newExtSystem, $extSystem);
        $this->validateDefaultVoiceFamily($extSystem, $newExtSystem);
        $this->validateTtsFreeAudioEpilogAssetType($newExtSystem->getTtsFreeAudioEpilogAsset());

        return $this->extSystemManager->update($extSystem, $newExtSystem);
    }

    /**
     * @throws ValidationException
     */
    private function validateDefaultVoiceFamily(ExtSystem $extSystem, ExtSystem $newExtSystem): void
    {
        $defaultVoiceFamilyId = $newExtSystem->getTtsSettings()->getDefaultVoiceFamilyId();
        if (null === $defaultVoiceFamilyId) {
            return;
        }

        $voiceFamily = $this->voiceFamilyRepository->find($defaultVoiceFamilyId);
        if (null === $voiceFamily) {
            throw (new ValidationException())
                ->addFormattedError('defaultVoiceFamilyId', ValidationException::ERROR_FIELD_VALUE_NOT_FOUND);
        }
        if ($voiceFamily->getExtSystem()->isNot($extSystem)) {
            throw (new ValidationException())
                ->addFormattedError('defaultVoiceFamilyId', ValidationException::ERROR_FIELD_INVALID);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTtsFreeAudioEpilogAssetType(?Asset $freeAudioEpilogAsset): void
    {
        if (null === $freeAudioEpilogAsset) {
            return;
        }

        if (false === $freeAudioEpilogAsset->getAssetType()->is(AssetType::Audio)) {
            throw (new ValidationException())
                ->addFormattedError('ttsFreeAudioEpilogAsset', ValidationException::ERROR_FIELD_INVALID);
        }
    }
}
