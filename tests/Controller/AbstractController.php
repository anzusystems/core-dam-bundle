<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller;

use AnzuSystems\CommonBundle\Tests\Traits\AnzuKernelTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Cache;

abstract class AbstractController extends WebTestCase
{
    use AnzuKernelTrait;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->beginTransaction();
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