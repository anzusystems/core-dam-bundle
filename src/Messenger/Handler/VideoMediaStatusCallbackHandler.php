<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoMediaStatusCallbackMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Delivers media-status callback to ext-system; lets exceptions bubble so the transport retries. */
#[AsMessageHandler]
final readonly class VideoMediaStatusCallbackHandler
{
    public function __construct(
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    public function __invoke(VideoMediaStatusCallbackMessage $message): void
    {
        $this->extSystemCallbackFacade->notifyMediaStatus(
            extSystemId: $message->extSystemId,
            assetId: $message->assetId,
            status: $message->status,
            failureReason: $message->failureReason,
            originAssetId: $message->originAssetId,
            originAssetFileId: $message->originAssetFileId,
        );
    }
}
