<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\AbstractFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use Doctrine\Common\Collections\Collection;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/** Per-chunk MP3 IO: persist blobs, ffmpeg-concat to master, purge on cleanup; keyed by ext-system. */
final readonly class TtsChunkStorage
{
    private const string PATH_PREFIX = 'tts/chunk/';

    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private ExtSystemConfigurationProvider $extSystemConfigProvider,
        private FfmpegService $ffmpegService,
    ) {
    }

    /**
     * @throws TtsProviderException
     * @throws FilesystemException
     */
    public function write(ExtSystem $extSystem, string $requestId, int $ordinal, string $bytes): string
    {
        $path = sprintf('%s%s/%d.mp3', self::PATH_PREFIX, $requestId, $ordinal);
        $this->resolve($extSystem)->write($path, $bytes);

        return $path;
    }

    /**
     * @param Collection<int, TtsSynthesisChunk> $orderedChunks
     *
     * @throws TtsProviderException
     */
    public function concatToMaster(ExtSystem $extSystem, Collection $orderedChunks): AdapterFile
    {
        $storage = $this->resolve($extSystem);
        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();

        try {
            $parts = [];
            foreach ($orderedChunks as $chunk) {
                $rel = $tmpFs->writeTmpFileFromBytes($storage->read((string) $chunk->getMp3StoragePath()), FfmpegService::AUDIO_EXTENSION_MP3);
                $parts[] = new File($tmpFs->extendPath($rel));
            }

            return $this->ffmpegService->concatAudio($parts);
        } catch (FilesystemException | FfmpegException $e) {
            throw new TtsProviderException('TTS chunk concat failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param list<string> $paths
     *
     * @throws TtsProviderException
     * @throws FilesystemException
     */
    public function delete(ExtSystem $extSystem, array $paths): void
    {
        if ([] === $paths) {
            return;
        }
        $storage = $this->resolve($extSystem);
        foreach ($paths as $path) {
            $storage->delete($path);
        }
    }

    /**
     * @throws TtsProviderException
     */
    private function resolve(ExtSystem $extSystem): AbstractFilesystem
    {
        $name = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->chunkStorageName;
        $storage = $this->fileSystemProvider->getFileSystemByStorageName($name);
        if (null === $storage) {
            throw new TtsProviderException(sprintf('TTS chunk storage "%s" is not registered (ExtSystem "%s").', $name, $extSystem->getSlug()));
        }

        return $storage;
    }
}
