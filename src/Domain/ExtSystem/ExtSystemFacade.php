<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\ExtSystem\ExtSystemTtsSettingsUpdateDto;
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

        return $this->extSystemManager->update($extSystem, $newExtSystem);
    }

    /**
     * @throws ValidationException
     */
    public function updateTtsSettings(ExtSystem $extSystem, ExtSystemTtsSettingsUpdateDto $dto): ExtSystem
    {
        $this->validator->validate($dto);
        $this->validateDefaultVoiceFamily($extSystem, $dto);

        return $this->extSystemManager->updateTtsSettings($extSystem, $dto);
    }

    /**
     * @throws ValidationException
     */
    private function validateDefaultVoiceFamily(ExtSystem $extSystem, ExtSystemTtsSettingsUpdateDto $dto): void
    {
        $defaultVoiceFamilyId = $dto->getDefaultVoiceFamilyId();
        if (null === $defaultVoiceFamilyId) {
            return;
        }

        $voiceFamily = $this->voiceFamilyRepository->find($defaultVoiceFamilyId);
        if (null === $voiceFamily || $voiceFamily->getExtSystem()->isNot($extSystem)) {
            throw (new ValidationException())
                ->addFormattedError('defaultVoiceFamilyId', ValidationException::ERROR_FIELD_VALUE_NOT_FOUND);
        }
    }
}
