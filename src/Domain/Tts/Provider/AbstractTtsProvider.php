<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\TmpLocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Shared infrastructure for TTS providers — chunk persistence + ffmpeg concat. Subclasses iterate
 * chunks their own way (ElevenLabs chains `previous_request_ids`; Google is stateless) and yield
 * raw MP3 bytes; this base handles tmp lifecycle uniformly.
 */
abstract class AbstractTtsProvider implements TtsProviderInterface
{
    public function __construct(
        protected readonly FileSystemProvider $fileSystemProvider,
        protected readonly FfmpegService $ffmpegService,
    ) {
    }

    /**
     * Writes a single MP3 bytes blob into the tmp filesystem and returns the {@see AdapterFile}
     * pointer — downstream consumers stream from disk instead of holding the full payload in memory.
     *
     * @throws FilesystemException
     */
    protected function writeSingleChunk(string $bytes): AdapterFile
    {
        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();
        $rel = $tmpFs->writeTmpFileFromBytes($bytes, FfmpegService::AUDIO_EXTENSION_MP3);

        return new AdapterFile(
            path: $tmpFs->extendPath($rel),
            adapterPath: $rel,
            filesystem: $tmpFs,
        );
    }

    /**
     * @param iterable<string> $bytesChunks ordered MP3 byte blobs (one per chunk)
     *
     * @throws TtsProviderException
     */
    protected function concatChunks(iterable $bytesChunks): AdapterFile
    {
        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();

        try {
            $parts = [];
            foreach ($bytesChunks as $bytes) {
                $rel = $tmpFs->writeTmpFileFromBytes($bytes, FfmpegService::AUDIO_EXTENSION_MP3);
                $parts[] = new File($tmpFs->extendPath($rel));
            }

            return $this->ffmpegService->concatAudio($parts);
        } catch (FilesystemException | FfmpegException $e) {
            $tmpFs->tryClearPaths();

            throw new TtsProviderException('TTS chunk concat failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
