<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use Doctrine\ORM\EntityManagerInterface;

final class VoiceFamilyFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly VoiceFamilyManager $voiceFamilyManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(VoiceFamily $voiceFamily): VoiceFamily
    {
        $this->validator->validate($voiceFamily);
        $this->voiceFamilyManager->create($voiceFamily);

        return $voiceFamily;
    }

    /**
     * Validates the existing entity post-copy — it carries the immutable slug/extSystem the payload omits.
     *
     * @throws ValidationException
     */
    public function update(VoiceFamily $voiceFamily, VoiceFamily $newVoiceFamily): VoiceFamily
    {
        $this->voiceFamilyManager->update($voiceFamily, $newVoiceFamily, flush: false);
        $this->validator->validate($voiceFamily);
        $this->entityManager->flush();

        return $voiceFamily;
    }

    public function delete(VoiceFamily $voiceFamily): bool
    {
        return $this->voiceFamilyManager->delete($voiceFamily);
    }
}
