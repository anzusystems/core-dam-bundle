<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Distribution;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use PHPUnit\Framework\TestCase;

final class AbstractDistributionModuleTest extends TestCase
{
    public function testFreshRemoteProcessingKeepsWaiting(): void
    {
        $distribution = (new YoutubeDistribution())->setModifiedAt(App::getAppDate()->modify('-2 hours'));

        self::module()->exposedThrowWhenRemoteProcessingExpired($distribution);
        $this->addToAssertionCount(1);
    }

    public function testExpiredRemoteProcessingFails(): void
    {
        $distribution = (new YoutubeDistribution())->setModifiedAt(App::getAppDate()->modify('-4 hours'));

        $this->expectException(RemoteProcessingFailedException::class);

        self::module()->exposedThrowWhenRemoteProcessingExpired($distribution);
    }

    private static function module(): object
    {
        return new class extends AbstractDistributionModule {
            public function exposedThrowWhenRemoteProcessingExpired(Distribution $distribution): void
            {
                $this->throwWhenRemoteProcessingExpired($distribution);
            }

            public function distribute(Distribution $distribution): void
            {
            }

            public function supportsAssetType(): array
            {
                return [];
            }
        };
    }
}
