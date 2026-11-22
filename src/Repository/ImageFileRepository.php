<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;

/**
 * @extends AbstractAssetFileRepository<ImageFile>
 * @method ImageFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ImageFile|null findProcessedById(string $id)
 * @method ImageFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ImageFileRepository extends AbstractAssetFileRepository
{
    /**
     * @return Collection<int, ImageFile>
     */
    public function findByLicenceAndIds(AssetLicence $assetLicence, array $ids): Collection
    {
        /** @var array<ImageFile> $result */
        $result = $this->findBy(
            [
                'licence' => $assetLicence,
                'id' => $ids,
            ]
        );

        return new ArrayCollection($result);
    }

    /**
     * The same bytes in a licence of the same ext system that forbids direct use, a file taken over from an agency.
     */
    public function findProcessedTakeOverSourceByChecksum(string $checksum, AssetLicence $licence): ?ImageFile
    {
        /** @var ImageFile|null $result */
        $result = $this->createQueryBuilder('entity')
            ->join('entity.licence', 'licence')
            ->where('entity.assetAttributes.checksum = :checksum')
            ->andWhere('entity.assetAttributes.status = :status')
            ->andWhere('IDENTITY(licence.extSystem) = :extSystemId')
            ->andWhere('licence.flags.directUseAllowed = false')
            ->setParameter('checksum', $checksum)
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->setParameter('extSystemId', $licence->getExtSystem()->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findProcessedByIdAndLicence(string $id, AssetLicence $licence): ?ImageFile
    {
        return $this->findOneBy([
            'id' => $id,
            'licence' => $licence,
            'assetAttributes.status' => AssetFileProcessStatus::Processed,
        ]);
    }

    public function findOneProcessedByUrlAndLicence(string $url, AssetLicence $licence): ?ImageFile
    {
        return $this->findOneBy([
            'assetAttributes.originUrl' => $url,
            'licence' => $licence,
            'assetAttributes.status' => AssetFileProcessStatus::Processed,
        ]);
    }

    /**
     * @return Collection<int, ImageFile>
     */
    public function findAllByLicence(
        AssetLicence $licence,
        int $limit,
        string $idFrom = '',
        ?DateTimeImmutable $createdFrom = null,
        ?DateTimeImmutable $createdUntil = null,
    ): Collection {
        $queryBuilder = $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.licence) = :licenceId')
            ->setParameter('licenceId', $licence->getId())
            ->orderBy('entity.id', Criteria::ASC)
            ->setMaxResults($limit);

        if (false === ('' === $idFrom)) {
            $queryBuilder
                ->andWhere('entity.id > :idFrom')
                ->setParameter('idFrom', $idFrom);
        }

        if ($createdFrom instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('entity.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $createdFrom);
        }

        if ($createdUntil instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('entity.createdAt <= :createdUntil')
                ->setParameter('createdUntil', $createdUntil);
        }

        return new ArrayCollection(
            $queryBuilder->getQuery()->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return ImageFile::class;
    }
}
