<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsMediaStatusCallbackMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Delivers TTS media-status callback to ext-system; lets exceptions bubble so the transport retries. */
#[AsMessageHandler]
final readonly class TtsMediaStatusCallbackHandler
{
    public function __construct(
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    public function __invoke(TtsMediaStatusCallbackMessage $message): void
    {
        $this->extSystemCallbackFacade->notifyMediaStatus(
            extSystemId: $message->extSystemId,
            assetId: $message->assetId,
            status: $message->status,
            failureReason: $message->failureReason,
        );
    }
}
