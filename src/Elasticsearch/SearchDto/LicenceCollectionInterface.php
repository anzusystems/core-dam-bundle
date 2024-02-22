<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use Doctrine\Common\Collections\Collection;

interface LicenceCollectionInterface
{
    /**
     * @return Collection<int, AssetLicence>
     */
    public function getLicences(): Collection;
}
