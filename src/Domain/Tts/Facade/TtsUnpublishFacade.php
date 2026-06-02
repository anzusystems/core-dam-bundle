<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TtsUnpublishFacade
{
    public function __construct(
        private TtsAssetLocker $ttsAssetLocker,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws RegenCancelledException if the asset is not a TTS asset
     */
    public function execute(Asset $asset, ?string $userId): void
    {
        App::throwOnReadOnlyMode();

        $this->entityManager->wrapInTransaction(function () use ($asset, $userId): void {
            $ttsAsset = $this->ttsAssetLocker->requireFor($asset);

            $this->ttsAssetManager->markUnpublished($ttsAsset);
            $this->auditLogger->logUnpublished((string) $asset->getId(), $userId);

            $this->entityManager->flush();
        });
    }
}
