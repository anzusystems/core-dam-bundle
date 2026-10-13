<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Model\Enum;

use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use PHPUnit\Framework\TestCase;

final class MediaStatusTypeTest extends TestCase
{
    // core-cms DamMediaStatusType mirrors these values; a mismatch ends in HTTP 422.
    public function testWireValuesAreStable(): void
    {
        self::assertSame('generationFailed', MediaStatusType::GenerationFailed->value);
        self::assertSame('videoFailed', MediaStatusType::VideoFailed->value);
        self::assertSame('videoDuplicate', MediaStatusType::VideoDuplicate->value);
        self::assertCount(3, MediaStatusType::cases());
    }
}
