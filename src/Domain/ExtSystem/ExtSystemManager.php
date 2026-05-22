<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\ExtSystem\ExtSystemTtsSettingsUpdateDto;
use Doctrine\Common\Collections\Collection;

final class ExtSystemManager extends AbstractManager
{
    public function create(ExtSystem $extSystem, bool $flush = true): ExtSystem
    {
        $this->trackCreation($extSystem);
        $this->entityManager->persist($extSystem);
        $this->flush($flush);

        return $extSystem;
    }

    public function update(ExtSystem $extSystem, ExtSystem $newExtSystem, bool $flush = true): ExtSystem
    {
        $this->trackModification($extSystem);
        $extSystem
            ->setName($newExtSystem->getName())
            ->setSlug($newExtSystem->getSlug())
        ;
        /** @psalm-suppress InvalidArgument */
        $this->colUpdate(
            oldCollection: $extSystem->getAdminUsers(),
            newCollection: $newExtSystem->getAdminUsers(),
            addElementFn: function (Collection $oldCollection, DamUser $newUser) use ($extSystem): bool {
                $newUser->getAdminToExtSystems()->add($extSystem);
                $oldCollection->add($newUser);

                return true;
            },
            removeElementFn: function (Collection $oldCollection, DamUser $oldUser) use ($extSystem): bool {
                $oldUser->getAdminToExtSystems()->removeElement($extSystem);
                $oldCollection->removeElement($oldUser);

                return true;
            }
        );
        $this->flush($flush);

        return $extSystem;
    }

    public function updateTtsSettings(ExtSystem $extSystem, ExtSystemTtsSettingsUpdateDto $dto, bool $flush = true): ExtSystem
    {
        $this->trackModification($extSystem);
        $extSystem->getTtsSettings()
            ->setAutoPodcastId($dto->getAutoPodcastId())
            ->setRecommendedPodcastId($dto->getRecommendedPodcastId())
            ->setDefaultVoiceFamilyId($dto->getDefaultVoiceFamilyId())
            ->setActiveProviderMode($dto->getActiveProviderMode());
        $extSystem->setTtsDefaultAssetLicence($dto->getTtsDefaultAssetLicence());
        $this->flush($flush);

        return $extSystem;
    }
}
