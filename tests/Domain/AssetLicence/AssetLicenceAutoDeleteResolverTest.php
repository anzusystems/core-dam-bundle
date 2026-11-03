<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetLicence;

use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceAutoDeleteResolver;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetLicenceAutoDeleteResolverTest extends TestCase
{
    private const string CREATED_AT = '2026-10-01T08:00:00+00:00';

    #[DataProvider('provideDeleteAt')]
    public function testDeleteAt(bool $active, int $olderThanDays, ?string $expectedDeleteAt): void
    {
        $autoDelete = (new AssetLicenceAutoDelete())
            ->setActive($active)
            ->setOlderThanDays($olderThanDays);
        $asset = (new Asset())->setLicence((new AssetLicence())->setAutoDelete($autoDelete));
        $asset->setCreatedAt(new DateTimeImmutable(self::CREATED_AT));
        $resolver = new AssetLicenceAutoDeleteResolver();

        self::assertSame(null !== $expectedDeleteAt, $resolver->isEffective($autoDelete));
        self::assertSame($expectedDeleteAt, $resolver->getDeleteAt($asset)?->format(DATE_ATOM));
    }

    public static function provideDeleteAt(): iterable
    {
        yield 'active, 30 days' => [true, 30, '2026-10-31T08:00:00+00:00'];
        yield 'active, at the minimum' => [true, AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS, '2026-10-03T08:00:00+00:00'];
        yield 'active, below the minimum' => [true, AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS - 1, null];
        yield 'inactive' => [false, 30, null];
    }
}
