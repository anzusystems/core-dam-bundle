<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain;

use AnzuSystems\CommonBundle\Domain\AbstractManager as BaseAbstractManager;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Event\UserTrackingEvent;
use ArrayIterator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractManager extends BaseAbstractManager
{
    protected EventDispatcherInterface $eventDispatcher;
    private CurrentAnzuUserProvider $currentUser;
    private RequestStack $requestStack;

    #[Required]
    public function setCurrentUser(CurrentAnzuUserProvider $currentUser): self
    {
        $this->currentUser = $currentUser;

        return $this;
    }

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    #[Required]
    public function setRequestStack(RequestStack $requestStack): self
    {
        $this->requestStack = $requestStack;

        return $this;
    }

    /**
     * @template T of PositionableInterface
     *
     * @param Collection<int, T> $coll
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

    public function trackCreation(object $object): void
    {
        parent::trackCreation($object);
        if ($object instanceof UserTrackingInterface) {
            /** @var DamUser $createdBy */
            $createdBy = $object->getCreatedBy();
            $event = new UserTrackingEvent($createdBy, $object);
            $this->eventDispatcher->dispatch($event);
            $object->setModifiedBy($event->getUser());
        }

        $this->trackNotifyTo($object);
    }

    public function trackModification(object $object): void
    {
        parent::trackModification($object);
        if ($object instanceof UserTrackingInterface) {
            /** @var DamUser $modifiedBy */
            $modifiedBy = $object->getModifiedBy();
            $event = new UserTrackingEvent($modifiedBy, $object);
            $this->eventDispatcher->dispatch($event);
            $object->setModifiedBy($event->getUser());
        }

        $this->trackNotifyTo($object);
    }

    private function trackNotifyTo(object $object): void
    {
        if ($object instanceof NotifiableInterface && $this->requestStack->getCurrentRequest()) {
            /** @var DamUser $currentUser */
            $currentUser = $this->currentUser->getCurrentUser();
            $object->setNotifyTo($currentUser);
        }
    }
}
