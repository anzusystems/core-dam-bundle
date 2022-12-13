<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface IndexFactoryInterface
{
    public function buildFromEntity(ExtSystemIndexableInterface $entity): array;

    public static function getDefaultKeyName(): string;
}
