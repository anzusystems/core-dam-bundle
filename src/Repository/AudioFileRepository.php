<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;

/**
 * @extends AbstractAssetFileRepository<AudioFile>
 *
 * @method AudioFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AudioFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AudioFile|null findProcessedById(string $id)
 * @method AudioFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AudioFileRepository extends AbstractAssetFileRepository
{
    /**
     * Audio files whose retention grace has elapsed — superseded TTS masters/previews kept alive after a
     * regeneration (see {@see AudioFile::getExpireAt()}). Oldest first, batched for the cron reaper.
     *
     * @return Collection<int, AudioFile>
     */
    public function findExpired(DateTimeInterface $now, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->andWhere('entity.expireAt IS NOT NULL')
                ->andWhere('entity.expireAt < :now')
                ->setParameter('now', $now)
                ->addOrderBy('entity.expireAt', Criteria::ASC)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }
    protected function getEntityClass(): string
    {
        return AudioFile::class;
    }
}
