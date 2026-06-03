<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsMediaStatusCallbackMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Delivers a TTS media-status callback to the ext-system. Calls the throwing {@see ExtSystemCallbackFacade::notifyMediaStatus()}
 * so a failed delivery (CMS unreachable) bubbles up and the transport retries it — the standard pub/sub flow.
 */
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
