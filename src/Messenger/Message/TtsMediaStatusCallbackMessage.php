<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;

/** Out-of-band media-status notification to ext-system; retried by transport on transient CMS outage. */
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
