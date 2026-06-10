<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;

/** Shared TTS-provider base: dispatch-time chunk-storage config check. */
abstract class AbstractTtsProvider implements TtsProviderInterface
{
    public function __construct(
        protected readonly FileSystemProvider $fileSystemProvider,
        protected readonly ExtSystemConfigurationProvider $extSystemConfigProvider,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    protected function assertChunkStorageConfigured(ExtSystem $extSystem): void
    {
        $storageName = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->chunkStorageName;
        if ('' === $storageName) {
            throw new TtsProviderException(sprintf('No TTS chunk storage configured for ExtSystem "%s".', $extSystem->getSlug()));
        }
        if (null === $this->fileSystemProvider->getFileSystemByStorageName($storageName)) {
            throw new TtsProviderException(sprintf('TTS chunk storage "%s" is not registered (ExtSystem "%s").', $storageName, $extSystem->getSlug()));
        }
    }
}
