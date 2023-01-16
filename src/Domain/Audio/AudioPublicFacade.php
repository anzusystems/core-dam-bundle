<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Throwable;

final class AudioPublicFacade
{
    use IndexManagerAwareTrait;

    public const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly EntityValidator $validator,
        private readonly AudioManager $audioManager,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function makePublic(AudioFile $audio, AudioPublicationAdmDto $dto): AudioFile
    {
        $this->validateProcessState($audio);
        $this->validateTransition($audio, false);
        $this->validator->validateDto($dto);

        try {
            $this->audioManager->beginTransaction();

            $this->makeEntityPublic($audio, $dto);
            $this->writeAudioStream($audio);

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

            $this->makeEntityPrivate($audio);
            $this->deleteAudioStream($audio);
            // todo save existing
            $this->audioManager->flush();
            $this->indexManager->index($audio->getAsset());
            $this->audioManager->commit();
        } catch (Throwable $exception) {
            $this->audioManager->rollback();

            throw new RuntimeException('make_private_audio_failed', 0, $exception);
        }

        return $audio;
    }

    /**
     * @throws FilesystemException
     */
    public function writeAudioStream(AudioFile $audio): void
    {
        $path = $audio->getAudioPublicLink()->getPath();
        $publicFilesystem = $this->fileSystemProvider->getPublicFilesystem($audio);

        if ($publicFilesystem->has($path)) {
            $publicFilesystem->delete($path);
        }

        $publicFilesystem->writeStream(
            $path,
            $this->fileSystemProvider->getFilesystemByStorable(
                $audio
            )->readStream($audio->getAssetAttributes()->getFilePath())
        );

        // todo purge cache
        // $this->cachePurgeManager->purgeAudioCache((string) $audio->getId(), $path);
    }

    /**
     * @throws FilesystemException
     */
    public function deleteAudioStream(AudioFile $audio): void
    {
        $path = $audio->getAudioPublicLink()->getPath();
        $filesystem = $this->fileSystemProvider->getPublicFilesystem($audio);

        if ($filesystem->has($path)) {
            $filesystem->delete($path);
        }

        // todo purge cache
        // $this->cachePurgeManager->purgeAudioCache($audio->getId(), $path);
    }

    private function makeEntityPublic(AudioFile $audio, AudioPublicationAdmDto $dto): void
    {
        $path = sprintf(
            '%s/%s.%s',
            $audio->getId(),
            $dto->getSlug(),
            FileHelper::guessExtension($audio->getAssetAttributes()->getMimeType())
        );

        $audio->getAudioPublicLink()
            ->setSlug($dto->getSlug())
            ->setPublic(true)
            ->setPath($path);
    }

    private function makeEntityPrivate(AudioFile $audio): void
    {
        $audio->getAudioPublicLink()
            ->setPublic(false);
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
