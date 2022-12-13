<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Chunk;

/**
 * @extends AbstractAnzuRepository<Chunk>
 *
 * @method Chunk|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chunk|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method Chunk|null findProcessedById(string $id)
 * @method Chunk|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ChunkRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return Chunk::class;
    }
}
