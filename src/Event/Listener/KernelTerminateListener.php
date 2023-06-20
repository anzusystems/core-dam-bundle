<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Listener;

use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use League\Flysystem\FilesystemException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

#[AsEventListener(event: TerminateEvent::class)]
final readonly class KernelTerminateListener
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(TerminateEvent $event): void
    {
        $this->fileSystemProvider->getTmpFileSystem()->clearPaths();
    }
}
