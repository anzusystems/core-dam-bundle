<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;

/**
 * Out-of-band media-status notification to the ext-system (CMS), delivered via pub/sub so a transient CMS
 * outage is retried by the transport instead of being silently dropped. Carries only scalars + the assetId
 * (content-addressed model) so it is safely serializable and idempotent on redelivery.
 */
final readonly class TtsMediaStatusCallbackMessage
{
    public function __construct(
        public int $extSystemId,
        public string $assetId,
        public MediaStatusType $status,
        public ?string $failureReason = null,
    ) {
    }
}
