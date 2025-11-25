<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
        return new ArrayCollection($this->findBy(
            [
                'licence' => $assetLicence,
                'id' => $ids,
            ]
        ));
    }

    public function findOneProcessedByUrlAndLicence(string $url, AssetLicence $licence): ?ImageFile
    {
        return $this->findOneBy([
            'assetAttributes.originUrl' => $url,
            'licence' => $licence,
            'assetAttributes.status' => AssetFileProcessStatus::Processed,
        ]);
    }

    protected function getEntityClass(): string
    {
        return ImageFile::class;
    }
}
