<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use Doctrine\ORM\Exception\ORMException;

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
    public function getUploadedSizeByAssetFile(AssetFile $assetFile): int
    {
        try {
            return (int) $this->createQueryBuilder('entity')
                ->select('SUM(entity.size)')
                ->where('IDENTITY(entity.assetFile) = :assetFileId')
                ->setParameter('assetFileId', $assetFile->getId())
                ->getQuery()->getSingleScalarResult();
        } catch (ORMException) {
            return 0;
        }
    }

    protected function getEntityClass(): string
    {
        return Chunk::class;
    }
}
