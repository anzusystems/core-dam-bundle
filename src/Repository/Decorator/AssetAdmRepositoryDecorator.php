<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\Common\Collections\Collection;

final class AssetAdmRepositoryDecorator
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function findByLicenceAndIds(AssetLicence $assetLicence, array $ids): ApiResponseList
    {
        return $this->decorate(
            $this->assetRepository->findByLicenceAndIds($assetLicence, $ids)
        );
    }

    public function findByExtSystemAndIds(ExtSystem $extSystem, array $ids): ApiResponseList
    {
        return $this->decorate(
            $this->assetRepository->findByExtSystemAndIds($extSystem, $ids)
        );
    }

    private function decorate(Collection $list): ApiResponseList
    {
        return (new ApiResponseList())
            ->setTotalCount($list->count())
            ->setData(
                $list->map(
                    fn (Asset $asset): AssetAdmDetailDto => AssetAdmDetailDto::getInstance($asset),
                )->toArray()
            );
    }
}
