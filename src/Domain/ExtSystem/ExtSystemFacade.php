<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
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
        $this->assertBelongsToExtSystem($extSystem, $newExtSystem->getTtsDefaultAssetLicence(), 'ttsDefaultAssetLicence');
        $this->validateTtsAdvertAsset($extSystem, $newExtSystem->getTtsAdvertAsset());

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
        $this->assertBelongsToExtSystem($extSystem, $voiceFamily, 'defaultVoiceFamilyId');
    }

    /**
     * @throws ValidationException
     */
    private function validateTtsAdvertAsset(ExtSystem $extSystem, ?Asset $advertAsset): void
    {
        if (null === $advertAsset) {
            return;
        }

        $this->assertBelongsToExtSystem($extSystem, $advertAsset, 'ttsAdvertAsset');

        if (false === $advertAsset->getAssetType()->is(AssetType::Audio)) {
            throw (new ValidationException())
                ->addFormattedError('ttsAdvertAsset', ValidationException::ERROR_FIELD_INVALID);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertBelongsToExtSystem(ExtSystem $extSystem, ?ExtSystemInterface $entity, string $field): void
    {
        if (null === $entity) {
            return;
        }
        if ($entity->getExtSystem()->isNot($extSystem)) {
            throw (new ValidationException())
                ->addFormattedError($field, ValidationException::ERROR_FIELD_INVALID);
        }
    }
}
