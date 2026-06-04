<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * Pub/Sub redelivery may bring the same chunk message twice; both workers serialise on
     * this row so only one wins the claim.
     */
    public function findForUpdate(string $id): ?TtsSynthesisChunk
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Lowest-ordinal Pending chunk for a request — the sequential dispatcher looks this up after
     * marking its own chunk Done to dispatch the next one (null = all chunks done → assemble).
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
     * @return Collection<int, TtsSynthesisChunk> Done chunks in ordinal order — input for ffmpeg concat.
     */
    public function findAllDoneOrdered(string $requestId): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('c')
                ->where('IDENTITY(c.request) = :rid')
                ->andWhere('c.status = :done')
                ->setParameter('rid', $requestId)
                ->setParameter('done', TtsChunkStatus::Done)
                ->orderBy('c.ordinal', 'ASC')
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * The ≤{@see $limit} most recent Done chunks before {@see $beforeOrdinal}, oldest-first — the
     * ElevenLabs `previous_request_ids` chain for the chunk about to be synthesised.
     *
     * @return list<string>
     */
    public function findChainRequestIds(string $requestId, int $beforeOrdinal, int $limit): array
    {
        /** @var list<string> $ids */
        $ids = $this->createQueryBuilder('c')
            ->select('c.externalRequestId')
            ->where('IDENTITY(c.request) = :rid')
            ->andWhere('c.status = :done')
            ->andWhere('c.ordinal < :ord')
            ->andWhere('c.externalRequestId IS NOT NULL')
            ->setParameter('rid', $requestId)
            ->setParameter('done', TtsChunkStatus::Done)
            ->setParameter('ord', $beforeOrdinal)
            ->orderBy('c.ordinal', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return array_reverse(array_map(strval(...), $ids));
    }

    /**
     * Derived progress counts in one query (indexed by IDX_tts_chunk_request_status). total=0 → single-run.
     *
     * @return array{total: int, done: int, failed: int}
     */
    public function progressCounts(string $requestId): array
    {
        /** @var array{total: int|string, done: int|string|null, failed: int|string|null} $row */
        $row = $this->createQueryBuilder('c')
            ->select('COUNT(c.id) AS total')
            ->addSelect('SUM(CASE WHEN c.status = :done THEN 1 ELSE 0 END) AS done')
            ->addSelect('SUM(CASE WHEN c.status = :failed THEN 1 ELSE 0 END) AS failed')
            ->where('IDENTITY(c.request) = :rid')
            ->setParameter('rid', $requestId)
            ->setParameter('done', TtsChunkStatus::Done)
            ->setParameter('failed', TtsChunkStatus::Failed)
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total' => (int) $row['total'],
            'done' => (int) $row['done'],
            'failed' => (int) $row['failed'],
        ];
    }

    /**
     * @return Collection<int, TtsSynthesisChunk> all chunks of a request — failure-path blob purge.
     */
    public function findAllByRequest(string $requestId): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('c')
                ->where('IDENTITY(c.request) = :rid')
                ->setParameter('rid', $requestId)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Cron sweeper input: chunks stuck Pending despite the request being live (worker never picked
     * up the message, or chain dispatch was missed). Threshold computed by the caller.
     *
     * @return Collection<int, TtsSynthesisChunk>
     */
    public function findStuckPending(DateTimeImmutable $threshold, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('c')
                ->where('c.status = :pending')
                ->andWhere('c.modifiedAt < :threshold')
                ->setParameter('pending', TtsChunkStatus::Pending)
                ->setParameter('threshold', $threshold)
                ->orderBy('c.modifiedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Cron sweeper input: chunks claimed but never finished (worker crashed mid-synth). Caller picks
     * a generous threshold — false positives steal work from a still-live worker.
     *
     * @return Collection<int, TtsSynthesisChunk>
     */
    public function findStuckProcessing(DateTimeImmutable $threshold, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('c')
                ->where('c.status = :processing')
                ->andWhere('c.startedAt < :threshold')
                ->setParameter('processing', TtsChunkStatus::Processing)
                ->setParameter('threshold', $threshold)
                ->orderBy('c.startedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return TtsSynthesisChunk::class;
    }
}
