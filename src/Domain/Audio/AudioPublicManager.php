<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use League\Flysystem\FilesystemException;

final class AudioPublicManager
{
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly EntityValidator $validator,
        private readonly AudioManager $audioManager,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function makePublic(AudioFile $audio, AudioPublicationAdmDto $dto): AudioFile
    {
        $this->makeEntityPublic($audio, $dto);
        $this->writeAudioStream($audio);

        return $audio;
    }

    /**
     * @throws FilesystemException
     */
    public function makePrivate(AudioFile $audio): AudioFile
    {
        $this->makeEntityPrivate($audio);
        $this->deleteAudioStream($audio);

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
}
