<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain;

use AnzuSystems\CommonBundle\Domain\AbstractManager as BaseAbstractManager;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use ArrayIterator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractManager extends BaseAbstractManager
{
    private CurrentAnzuUserProvider $currentUser;

    #[Required]
    public function setCurrentUser(CurrentAnzuUserProvider $currentUser): self
    {
        $this->currentUser = $currentUser;

        return $this;
    }

    /**
     * @param Collection<int, PositionableInterface> $coll
     *
     * @throws Exception
     */
    public function reorderPositionedColl(Collection $coll): ArrayCollection
    {
        /** @var ArrayIterator $iterator */
        $iterator = $coll->getIterator();
        $iterator->uasort(
            fn (PositionableInterface $firstItem, PositionableInterface $secondItem) => $firstItem->getPosition() <=> $secondItem->getPosition()
        );

        $i = 0;
        /** @var PositionableInterface $item */
        foreach ($iterator as $item) {
            $item->setPosition(++$i);
        }

        return new ArrayCollection(iterator_to_array($iterator));
    }

    public function setNotifyTo(NotifiableInterface $object): void
    {
        $currentUser = $this->currentUser->getCurrentUser();
        if ($currentUser instanceof DamUser) {
            $object->setNotifyTo($currentUser);
        }
    }
}
