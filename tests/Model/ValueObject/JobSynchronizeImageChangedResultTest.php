<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Model\ValueObject;

use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobSynchronizeImageChangedResult;
use PHPUnit\Framework\TestCase;

final class JobSynchronizeImageChangedResultTest extends TestCase
{
    public function testToStringAndFromStringRoundtrip(): void
    {
        $result = new JobSynchronizeImageChangedResult(5, 100);
        $this->assertSame('5|100', $result->toString());

        $fromString = JobSynchronizeImageChangedResult::fromString('5|100');
        $this->assertSame(5, $fromString->getNotifiedCount());
        $this->assertSame(100, $fromString->getTotalCount());
    }

    public function testFromStringHandlesMissingParts(): void
    {
        $fromSingle = JobSynchronizeImageChangedResult::fromString('7');
        $this->assertSame(7, $fromSingle->getNotifiedCount());
        $this->assertSame(0, $fromSingle->getTotalCount());

        $fromEmpty = JobSynchronizeImageChangedResult::fromString('');
        $this->assertSame(0, $fromEmpty->getNotifiedCount());
        $this->assertSame(0, $fromEmpty->getTotalCount());
    }

    public function testDefaultValues(): void
    {
        $result = new JobSynchronizeImageChangedResult();
        $this->assertSame(0, $result->getNotifiedCount());
        $this->assertSame(0, $result->getTotalCount());
        $this->assertSame('0|0', $result->toString());
    }

    public function testIsAndIsNot(): void
    {
        $result = new JobSynchronizeImageChangedResult(1, 2);
        $this->assertTrue($result->is('1|2'));
        $this->assertFalse($result->isNot('1|2'));

        $this->assertFalse($result->is('0|0'));
        $this->assertTrue($result->isNot('0|0'));
    }

    public function testEquals(): void
    {
        $result1 = new JobSynchronizeImageChangedResult(3, 7);
        $result2 = new JobSynchronizeImageChangedResult(3, 7);
        $result3 = new JobSynchronizeImageChangedResult(1, 2);

        $this->assertTrue($result1->equals($result2));
        $this->assertFalse($result1->equals($result3));
    }

    public function testInThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);

        $result = new JobSynchronizeImageChangedResult(1, 2);
        $result->in([]);
    }
}
