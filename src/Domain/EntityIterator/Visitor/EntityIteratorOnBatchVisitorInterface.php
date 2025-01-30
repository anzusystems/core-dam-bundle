<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\EntityIterator\Visitor;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EntityIteratorOnBatchVisitorInterface
{
    public function onBatch(EntityManagerInterface $entityManager): void;
}
