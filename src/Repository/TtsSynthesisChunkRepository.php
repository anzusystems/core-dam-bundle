<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;

/**
 * @extends AbstractAnzuRepository<TtsSynthesisChunk>
 *
 * @method TtsSynthesisChunk|null find($id, $lockMode = null, $lockVersion = null)
 * @method TtsSynthesisChunk|null findOneBy(array $criteria, array $orderBy = null)
 */
final class TtsSynthesisChunkRepository extends AbstractAnzuRepository
{
    /**
     * Pessimistic-write lock for handler-side claim transitions (Pending → Processing).
     * Pub/Sub redelivery may bring the same chunk message twice; both workers must serialise
     * on this row so only one wins the claim.
     */
    public function findForUpdate(string $id): ?TtsSynthesisChunk
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Lowest-ordinal Pending chunk for a request — sequential dispatcher (chunk handler #N
     * dispatches chunk #N+1) looks this up after marking its own chunk Done.
     */
    public function findNextPending(string $requestId): ?TtsSynthesisChunk
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.request) = :rid')
            ->andWhere('c.status = :pending')
            ->setParameter('rid', $requestId)
            ->setParameter('pending', TtsChunkStatus::Pending)
            ->orderBy('c.ordinal', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return list<TtsSynthesisChunk> Done chunks in ordinal order — input for ffmpeg concat.
     */
    public function findAllDoneOrdered(string $requestId): array
    {
        /** @var list<TtsSynthesisChunk> $rows */
        $rows = $this->createQueryBuilder('c')
            ->where('IDENTITY(c.request) = :rid')
            ->andWhere('c.status = :done')
            ->setParameter('rid', $requestId)
            ->setParameter('done', TtsChunkStatus::Done)
            ->orderBy('c.ordinal', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }

    public function countNotDone(string $requestId): int
    {
        return $this->count([
            'request' => $requestId,
            'status' => [TtsChunkStatus::Pending, TtsChunkStatus::Processing, TtsChunkStatus::Failed],
        ]);
    }

    public function countFailed(string $requestId): int
    {
        return $this->count([
            'request' => $requestId,
            'status' => TtsChunkStatus::Failed,
        ]);
    }

    /**
     * Cron sweeper input: chunks stuck Pending despite the request being live (worker never
     * picked up the message, or chain dispatch was missed). Threshold is computed by the caller
     * — mirrors {@see TtsAssetRepository::findStuckSuperseding}.
     *
     * @return list<TtsSynthesisChunk>
     */
    public function findStuckPending(DateTimeImmutable $threshold): array
    {
        /** @var list<TtsSynthesisChunk> $rows */
        $rows = $this->createQueryBuilder('c')
            ->where('c.status = :pending')
            ->andWhere('c.modifiedAt < :threshold')
            ->setParameter('pending', TtsChunkStatus::Pending)
            ->setParameter('threshold', $threshold)
            ->orderBy('c.modifiedAt', 'ASC')
            ->setMaxResults(500)
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }

    /**
     * Cron sweeper input: chunks claimed but never finished (worker crashed mid-synth). Caller
     * picks a generous threshold — false positives steal work from a still-live worker, true
     * positives recover stuck chunks.
     *
     * @return list<TtsSynthesisChunk>
     */
    public function findStuckProcessing(DateTimeImmutable $threshold): array
    {
        /** @var list<TtsSynthesisChunk> $rows */
        $rows = $this->createQueryBuilder('c')
            ->where('c.status = :processing')
            ->andWhere('c.startedAt < :threshold')
            ->setParameter('processing', TtsChunkStatus::Processing)
            ->setParameter('threshold', $threshold)
            ->orderBy('c.startedAt', 'ASC')
            ->setMaxResults(500)
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }

    protected function getEntityClass(): string
    {
        return TtsSynthesisChunk::class;
    }
}
