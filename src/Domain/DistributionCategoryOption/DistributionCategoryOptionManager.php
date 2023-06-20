<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategoryOption;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategoryOption;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use Doctrine\Common\Collections\Collection;
use Exception;

final class DistributionCategoryOptionManager extends AbstractManager
{
    /**
     * @throws Exception
     */
    public function createOptions(DistributionCategorySelect $select): void
    {
        $select->setOptions(
            $this->reorderPositionedColl($select->getOptions())
        );

        foreach ($select->getOptions() as $option) {
            $this->trackCreation($option);
            $option->setSelect($select);
            $this->entityManager->persist($option);
        }
    }

    /**
     * @throws Exception
     */
    public function updateOptions(DistributionCategorySelect $select, DistributionCategorySelect $newSelect): void
    {
        /** @psalm-suppress InvalidArgument */
        $this->colUpdate(
            oldCollection: $select->getOptions(),
            newCollection: $newSelect->getOptions(),
            updateElementFn: function (DistributionCategoryOption $oldOption, DistributionCategoryOption $newOption): bool {
                $this->trackModification($oldOption);
                $oldOption
                    ->setPosition($newOption->getPosition())
                    ->setName($newOption->getName())
                    ->setValue($newOption->getValue())
                    ->setAssignable($newOption->isAssignable())
                ;

                return true;
            },
            addElementFn: function (Collection $oldCollection, DistributionCategoryOption $newOption) use ($select): bool {
                $newOption->setSelect($select);
                $oldCollection->add($newOption);
                if (empty($newOption->getId())) {
                    $this->entityManager->persist($newOption);
                    $this->trackCreation($newOption);
                }

                return true;
            },
            removeElementFn: function (Collection $oldCollection, DistributionCategoryOption $oldOption): bool {
                $oldCollection->removeElement($oldOption);
                $this->entityManager->remove($oldOption);

                return true;
            }
        );
        $select->setOptions(
            $this->reorderPositionedColl($select->getOptions())
        );
    }

    public function deleteOptions(DistributionCategorySelect $select): void
    {
        foreach ($select->getOptions() as $option) {
            $select->getOptions()->removeElement($option);
            $this->entityManager->remove($option);
        }
    }
}
