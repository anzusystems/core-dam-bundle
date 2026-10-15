<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;

/** Out-of-band media-status notification to ext-system; retried by transport on transient CMS outage. */
final readonly class VideoMediaStatusCallbackMessage
{
    public const string FAILURE_REASON_DISTRIBUTION_FAILED = 'distribution_failed';

    public function __construct(
        public int $extSystemId,
        public string $assetId,
        public MediaStatusType $status,
        public ?string $failureReason = null,
        public ?string $originAssetId = null,
        public ?string $originAssetFileId = null,
    ) {
    }

    public static function fromVideoFile(
        VideoFile $videoFile,
        MediaStatusType $status,
        ?string $failureReason = null,
        ?VideoFile $origin = null,
    ): self {
        return new self(
            extSystemId: $videoFile->getExtSystem()->getId(),
            assetId: (string) $videoFile->getAsset()->getId(),
            status: $status,
            failureReason: $failureReason,
            originAssetId: $origin instanceof VideoFile ? (string) $origin->getAsset()->getId() : null,
            originAssetFileId: $origin instanceof VideoFile ? (string) $origin->getId() : null,
        );
    }
}
