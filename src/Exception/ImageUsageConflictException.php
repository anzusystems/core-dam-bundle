<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use DomainException;

/**
 * At least one single use photo of the batch is claimed by a holder other than the caller. Carries every
 * conflict of the batch, not just the first, so the caller can show the user all the blocking holders
 * without a second round trip.
 */
final class ImageUsageConflictException extends DomainException
{
    public const string ERROR_MESSAGE = 'image_usage_conflict';

    /**
     * @param list<ImageUsageConflictDto> $conflicts
     */
    public function __construct(
        private readonly array $conflicts,
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    /**
     * @return list<ImageUsageConflictDto>
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}
