<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use RuntimeException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

final class AudioPublicFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly AudioManager $audioManager,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AudioPublicManager $audioPublicManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function makePublic(AudioFile $audio, AudioPublicationAdmDto $dto): AudioFile
    {
        $this->validateProcessState($audio);
        $this->ensureSlug($audio, $dto);
        $this->validateTransition($audio, false);
        $this->validator->validate($dto);

        try {
            $this->audioManager->beginTransaction();

            $this->audioPublicManager->makePublic($audio, $dto);
            // todo purge cache
            // $this->cachePurgeManager->purgeAudioCache((string) $audio->getId(), $path);

            $this->audioManager->flush();
            $this->indexManager->index($audio->getAsset());

            $this->audioManager->commit();
        } catch (Throwable $exception) {
            $this->audioManager->rollback();

            throw new RuntimeException('make_public_audio_failed', 0, $exception);
        }

        return $audio;
    }

    public function makePrivate(AudioFile $audio): AudioFile
    {
        $this->validateTransition($audio, true);

        try {
            $this->audioManager->beginTransaction();

            $this->audioPublicManager->makePrivate($audio);

            // todo save existing
            // todo purge cache
            // $this->cachePurgeManager->purgeAudioCache($audio->getId(), $path);

            $this->audioManager->flush();
            $this->indexManager->index($audio->getAsset());
            $this->audioManager->commit();
        } catch (Throwable $exception) {
            $this->audioManager->rollback();

            throw new RuntimeException('make_private_audio_failed', 0, $exception);
        }

        return $audio;
    }

    private function ensureSlug(AudioFile $audio, AudioPublicationAdmDto $dto): void
    {
        if (empty($dto->getSlug())) {
            $dto->setSlug(
                $this->slugger->slug($audio->getAsset()->getTexts()->getDisplayTitle())->toString()
            );
        }
    }

    private function validateProcessState(AudioFile $audio): void
    {
        if ($audio->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }

    private function validateTransition(AudioFile $audio, bool $publicExpected): void
    {
        if ($audio->getAudioPublicLink()->isPublic() === $publicExpected) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }
}
