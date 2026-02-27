<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Model\ValueObject;

use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAssetFileReprocessInternalFlagResult;
use PHPUnit\Framework\TestCase;

final class JobAssetFileReprocessInternalFlagResultTest extends TestCase
{
    public function testToStringAndFromStringRoundtrip(): void
    {
        $result = new JobAssetFileReprocessInternalFlagResult(5, 100);
        $this->assertSame('5|100', $result->toString());

        $fromString = JobAssetFileReprocessInternalFlagResult::fromString('5|100');
        $this->assertSame(5, $fromString->getChangedCount());
        $this->assertSame(100, $fromString->getTotalCount());
    }

    public function testFromStringHandlesMissingParts(): void
    {
        $fromSingle = JobAssetFileReprocessInternalFlagResult::fromString('7');
        $this->assertSame(7, $fromSingle->getChangedCount());
        $this->assertSame(0, $fromSingle->getTotalCount());

        $fromEmpty = JobAssetFileReprocessInternalFlagResult::fromString('');
        $this->assertSame(0, $fromEmpty->getChangedCount());
        $this->assertSame(0, $fromEmpty->getTotalCount());
    }

    public function testDefaultValues(): void
    {
        $result = new JobAssetFileReprocessInternalFlagResult();
        $this->assertSame(0, $result->getChangedCount());
        $this->assertSame(0, $result->getTotalCount());
        $this->assertSame('0|0', $result->toString());
    }

    public function testIsAndIsNot(): void
    {
        $result = new JobAssetFileReprocessInternalFlagResult(1, 2);
        $this->assertTrue($result->is('1|2'));
        $this->assertFalse($result->isNot('1|2'));

        $this->assertFalse($result->is('0|0'));
        $this->assertTrue($result->isNot('0|0'));
    }

    public function testEquals(): void
    {
        $result1 = new JobAssetFileReprocessInternalFlagResult(3, 7);
        $result2 = new JobAssetFileReprocessInternalFlagResult(3, 7);
        $result3 = new JobAssetFileReprocessInternalFlagResult(1, 2);

        $this->assertTrue($result1->equals($result2));
        $this->assertFalse($result1->equals($result3));
    }

    public function testInThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);

        $result = new JobAssetFileReprocessInternalFlagResult(1, 2);
        $result->in([]);
    }
}
