<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;

/**
 * @extends AbstractAnzuRepository<ImageFile>
 *
 * @method ImageFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ImageFile|null findProcessedById(string $id)
 * @method ImageFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ImageFileRepository extends AbstractAssetFileRepository
{
    public function findOneByUrlAndLicence(string $url, AssetLicence $licence): ?ImageFile
    {
        return $this->findOneBy([
            'assetAttributes.originUrl' => $url,
            'licence' => $licence,
        ]);
    }

    protected function getEntityClass(): string
    {
        return ImageFile::class;
    }
}
