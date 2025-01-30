<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\EntityIterator\Visitor;

use Doctrine\ORM\EntityManagerInterface;

class EntityIteratorOnBatchFlushVisitor implements EntityIteratorOnBatchVisitorInterface
{
    public function onBatch(EntityManagerInterface $entityManager): void
    {
        $entityManager->flush();
        $entityManager->clear();
    }
}
