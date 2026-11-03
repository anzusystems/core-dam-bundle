<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetLicenceAutoDeleteTest extends TestCase
{
    private const string CREATED_AT = '2026-10-01T08:00:00+00:00';

    #[DataProvider('provideDeleteAt')]
    public function testDeleteAt(bool $active, int $olderThanDays, ?string $expectedDeleteAt): void
    {
        $autoDelete = (new AssetLicenceAutoDelete())
            ->setActive($active)
            ->setOlderThanDays($olderThanDays);

        self::assertSame(null !== $expectedDeleteAt, $autoDelete->isEffective());
        self::assertSame(
            $expectedDeleteAt,
            $autoDelete->getDeleteAt(new DateTimeImmutable(self::CREATED_AT))?->format(DATE_ATOM)
        );
    }

    public static function provideDeleteAt(): iterable
    {
        yield 'active, 30 days' => [true, 30, '2026-10-31T08:00:00+00:00'];
        yield 'active, at the minimum' => [true, AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS, '2026-10-03T08:00:00+00:00'];
        yield 'active, below the minimum' => [true, AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS - 1, null];
        yield 'inactive' => [false, 30, null];
    }
}
