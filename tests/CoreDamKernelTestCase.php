<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use Doctrine\ORM\Cache;
use Doctrine\ORM\EntityManagerInterface;

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
}