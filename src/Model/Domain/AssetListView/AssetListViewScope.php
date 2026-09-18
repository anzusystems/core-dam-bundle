<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetListView;

use AnzuSystems\CoreDamBundle\Entity\AssetListView;

final readonly class AssetListViewScope
{
    /**
     * @param list<int> $licenceIds licences of the view the user is actually allowed to read
     */
    public function __construct(
        private AssetListView $view,
        private array $licenceIds,
    ) {
    }

    public function getView(): AssetListView
    {
        return $this->view;
    }

    /**
     * @return list<int>
     */
    public function getLicenceIds(): array
    {
        return $this->licenceIds;
    }

    public function getUploadLicenceId(): ?int
    {
        $uploadLicence = $this->view->getUploadLicence();
        if (null === $uploadLicence) {
            return null;
        }

        $uploadLicenceId = (int) $uploadLicence->getId();
        if (false === in_array($uploadLicenceId, $this->licenceIds, true)) {
            return null;
        }

        return $uploadLicenceId;
    }
}
