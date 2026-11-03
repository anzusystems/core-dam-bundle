<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use DateTimeImmutable;

final readonly class AssetLicenceAutoDeleteResolver
{
    public function isEffective(AssetLicenceAutoDelete $autoDelete): bool
    {
        return $autoDelete->isActive() && $autoDelete->getOlderThanDays() >= AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS;
    }

    public function getCreatedBefore(AssetLicenceAutoDelete $autoDelete): DateTimeImmutable
    {
        return App::getAppDate()->modify(sprintf('-%d days', $autoDelete->getOlderThanDays()));
    }

    public function getDeleteAt(Asset $asset): ?DateTimeImmutable
    {
        $autoDelete = $asset->getLicence()->getAutoDelete();
        if (false === $this->isEffective($autoDelete)) {
            return null;
        }

        return $asset->getCreatedAt()->modify(sprintf('+%d days', $autoDelete->getOlderThanDays()));
    }
}
