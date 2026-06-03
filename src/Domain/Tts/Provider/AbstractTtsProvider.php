<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Shared TTS-provider base: chunk persistence + ffmpeg concat + tmp lifecycle. Subclasses yield raw MP3
 * bytes (ElevenLabs chains `previous_request_ids`; Google is stateless).
 */
abstract class AbstractTtsProvider implements TtsProviderInterface
{
    public function __construct(
        protected readonly FileSystemProvider $fileSystemProvider,
        protected readonly FfmpegService $ffmpegService,
        protected readonly ExtSystemConfigurationProvider $extSystemConfigProvider,
        protected readonly Config $config,
    ) {
    }

    /**
     * Effective per-request chunk size: the operator-driven {@see Config::getChunkSizeChars()} (env
     * `TTS_CHUNK_SIZE_CHARS`), clamped to this provider's hard API ceiling ({@see getMaxCharsPerRequest()})
     * so a misconfigured override can never exceed the documented per-request limit, and floored at 1 so a
     * zero/negative value can't break chunking. Lets ops steer chunk size (cost/latency) without a deploy.
     */
    protected function resolveChunkSize(): int
    {
        return max(1, min($this->config->getChunkSizeChars(), $this->getMaxCharsPerRequest()));
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
     * Validates per-extSystem chunk storage is configured + registered. Same storage is read back
     * by the assembler — keep in sync. Returns the resolved storage name for caller reuse.
     *
     * @throws TtsProviderException
     */
    protected function resolveChunkStorageName(ExtSystem $extSystem): string
    {
        $storageName = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->chunkStorageName;
        if ('' === $storageName) {
            throw new TtsProviderException(sprintf(
                'No TTS chunk storage configured for ExtSystem "%s".',
                $extSystem->getSlug(),
            ));
        }
        if (null === $this->fileSystemProvider->getFileSystemByStorageName($storageName)) {
            throw new TtsProviderException(sprintf(
                'TTS chunk storage "%s" is not registered (ExtSystem "%s").',
                $storageName,
                $extSystem->getSlug(),
            ));
        }

        return $storageName;
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
