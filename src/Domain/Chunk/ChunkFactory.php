<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;

final class ChunkFactory
{
    public function createFromAdmDto(ChunkAdmCreateDto $createDto): Chunk
    {
        return (new Chunk())
            ->setOffset($createDto->getOffset())
            ->setSize($createDto->getSize());
    }
}
