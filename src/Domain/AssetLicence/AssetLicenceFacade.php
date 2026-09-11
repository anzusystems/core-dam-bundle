<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;
use Doctrine\Common\Collections\ReadableCollection;

final class AssetLicenceFacade
{
    use ValidatorAwareTrait;

    public const string ERROR_EXT_SYSTEM_LOCKED_BY_LIST_VIEW = 'error_ext_system_locked_by_list_view';

    public function __construct(
        private readonly AssetLicenceManager $assetLicenceManager,
        private readonly AssetListViewRepository $assetListViewRepository,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetLicence $assetLicence): AssetLicence
    {
        $this->validator->validate($assetLicence);

        return $this->assetLicenceManager->create($assetLicence);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetLicence $assetLicence, AssetLicence $newAssetLicence): AssetLicence
    {
        $this->validator->validate($newAssetLicence, $assetLicence);
        if (
            $assetLicence->getExtSystem()->getId() !== $newAssetLicence->getExtSystem()->getId()
            && $this->assetListViewRepository->isLicenceUsed($assetLicence)
        ) {
            throw (new ValidationException())->addFormattedError('extSystem', self::ERROR_EXT_SYSTEM_LOCKED_BY_LIST_VIEW);
        }

        return $this->assetLicenceManager->update($assetLicence, $newAssetLicence);
    }

    /**
     * @param ReadableCollection<int, AssetLicence> $licences
     */
    public function deleteBulk(ReadableCollection $licences): void
    {
        foreach ($licences as $licence) {
            $this->assetLicenceManager->delete($licence, false);
        }
        $this->assetLicenceManager->flush();
    }
}
