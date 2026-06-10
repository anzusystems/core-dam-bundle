<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Helper;

use AnzuSystems\CoreDamBundle\Helper\DateTimeHelper;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateTimeHelperTest extends TestCase
{
    #[DataProvider('provideDurations')]
    public function testParseDurationToInterval(string $input, ?int $expectedSeconds): void
    {
        $interval = DateTimeHelper::parseDurationToInterval($input);

        if (null === $expectedSeconds) {
            self::assertNull($interval, sprintf('Expected "%s" to be unparseable.', $input));

            return;
        }

        self::assertNotNull($interval);
        $reference = new DateTimeImmutable('2020-01-01 00:00:00');
        self::assertSame(
            $expectedSeconds,
            $reference->add($interval)->getTimestamp() - $reference->getTimestamp(),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: int|null}>
     */
    public static function provideDurations(): iterable
    {
        yield 'hours only' => ['1h', 3_600];
        yield 'minutes only' => ['30m', 1_800];
        yield 'hours and minutes' => ['2h30m', 9_000];
        yield 'minutes overflow not normalised' => ['90m', 5_400];
        yield 'empty string' => ['', null];
        yield 'zero duration' => ['0h0m', null];
        yield 'non-duration text' => ['abc', null];
        yield 'wrong unit order' => ['30m1h', null];
        yield 'unsupported unit' => ['5s', null];
    }
}
