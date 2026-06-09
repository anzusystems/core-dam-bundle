<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestFactory;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchResult;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TtsRegenerationFacade
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsNarrationRequestFactory $requestFactory,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function regenerate(TtsSynthesizeRequestDto $dto, bool $enqueue = true): DispatchResult
    {
        App::throwOnReadOnlyMode();
        $regenerateAssetId = (string) $dto->getRegenerateAssetId();

        $request = $this->entityManager->wrapInTransaction(
            function () use ($dto, $regenerateAssetId): TtsNarrationRequest {
                $ttsAsset = $this->assetLocker->lockExpecting($regenerateAssetId, TtsLifecycle::ACTIVE_ONLY);
                $licence = $ttsAsset->getAsset()->getLicence();

                $request = $this->requestFactory->createRegenerate($dto, $licence, $regenerateAssetId);
                $this->requestManager->create(request: $request, flush: false);
                $this->ttsAssetManager->markSuperseding($ttsAsset);
                $this->entityManager->flush();

                return $request;
            }
        );

        if ($enqueue) {
            $this->messageBus->dispatch(new TtsNarrationRequestMessage((string) $request->getId()));
        }

        return DispatchResult::pending((string) $request->getAssetId(), $request);
    }
}
