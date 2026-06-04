<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsChunkStorage;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Best-effort teardown of a request's synthesis chunks — blobs (chunk storage) + rows. Called on the
 * happy path after assemble, on failure ({@see TtsRequestFailer}), and on cancel. Row delete is explicit
 * because the FK cascade only fires if the request itself is deleted (initial-failure path).
 */
final readonly class TtsChunkCleaner
{
    public function __construct(
        private TtsSynthesisChunkRepository $chunkRepo,
        private TtsChunkStorage $chunkStorage,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    public function purge(TtsNarrationRequest $request): void
    {
        try {
            $chunks = $this->chunkRepo->findAllByRequest((string) $request->getId());
            if ($chunks->isEmpty()) {
                return;
            }

            $paths = [];
            foreach ($chunks as $chunk) {
                $path = $chunk->getMp3StoragePath();
                if (null !== $path) {
                    $paths[] = $path;
                }
                $this->entityManager->remove($chunk);
            }
            $this->chunkStorage->delete($request->getLicence()->getExtSystem(), $paths);
            $this->entityManager->flush();
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'chunkCleanup.failed', [
                'requestId' => (string) $request->getId(),
            ], exception: $e);
        }
    }
}
