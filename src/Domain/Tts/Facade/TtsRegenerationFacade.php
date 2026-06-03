<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TtsRegenerationFacade
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetManager $ttsAssetManager,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function execute(
        string $stableAssetId,
        ?string $voiceFamilySlug,
    ): TtsNarrationRequest {
        App::throwOnReadOnlyMode();

        $request = $this->entityManager->wrapInTransaction(
            function () use ($stableAssetId, $voiceFamilySlug): TtsNarrationRequest {
                $ttsAsset = $this->assetLocker->lockExpecting($stableAssetId, TtsLifecycle::ACTIVE_ONLY);

                $assetLicence = $ttsAsset->getAsset()->getLicence();
                $request = (new TtsNarrationRequest())
                    ->setMode(TtsRequestMode::Regenerate)
                    ->setAssetId($stableAssetId)
                    ->setExtSystemId($assetLicence->getExtSystem()->getId())
                    ->setAssetLicence($assetLicence)
                    ->setVoiceFamilySlug($voiceFamilySlug);
                $this->requestManager->create(request: $request, flush: false);

                $this->ttsAssetManager->markSuperseding($ttsAsset);

                $this->entityManager->flush();

                return $request;
            }
        );

        $this->messageBus->dispatch(new TtsNarrationRequestMessage((string) $request->getId()));

        return $request;
    }
}
