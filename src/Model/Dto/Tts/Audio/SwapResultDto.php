<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;

final readonly class SwapResultDto
{
    /**
     * @param list<string>    $oldAudioFileIds   Stable AudioFile IDs — same IDs as before swap, now carrying new bytes.
     * @param list<string>    $newAudioFileIds   Staging AudioFile IDs that were deleted in the swap.
     * @param list<AudioFile> $audioFilesToPurge Entities whose routes now serve different content — callers
     *                                           must dispatch route-purge events for them post-commit.
     */
    public function __construct(
        public string $stableAssetId,
        public array $oldAudioFileIds,
        public array $newAudioFileIds,
        public array $audioFilesToPurge,
    ) {
    }
}
