<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\ORM\Cache;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

class CoreDamKernelTestCase extends AnzuKernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function cleanup(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->rollback();
        $cache = $this->entityManager->getCache();
        if ($cache instanceof Cache) {
            $cache->evictQueryRegions();
            $cache->evictEntityRegions();
            $cache->evictCollectionRegions();
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return T
     */
    protected function getService(string $id): object
    {
        return static::getContainer()->get($id);
    }

    protected function recordingDamLogger(TestHandler $journal, string $channel = 'test'): DamLogger
    {
        $damLogger = new DamLogger(new Logger($channel, [$journal]), $this->getService(LogContextFactory::class));
        $damLogger->setSerializer($this->getService(Serializer::class));

        return $damLogger;
    }
}
