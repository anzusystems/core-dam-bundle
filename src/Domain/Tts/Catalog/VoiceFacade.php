<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use Doctrine\ORM\EntityManagerInterface;

final class VoiceFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly VoiceManager $voiceManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(Voice $voice): Voice
    {
        $this->validator->validate($voice);
        $this->voiceManager->create($voice);

        return $voice;
    }

    /**
     * @throws ValidationException
     */
    public function update(Voice $voice, Voice $newVoice): Voice
    {
        $this->voiceManager->update($voice, $newVoice, flush: false);
        $this->validator->validate($voice);
        $this->entityManager->flush();

        return $voice;
    }

    public function delete(Voice $voice): bool
    {
        return $this->voiceManager->delete($voice);
    }
}
