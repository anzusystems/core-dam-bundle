<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Doctrine\Common\Collections\Collection;

final class CustomFormElementManager extends AbstractManager
{
    public function createElements(CustomForm $form): void
    {
        foreach ($form->getElements() as $element) {
            $this->createElement($form, $element);
        }
    }

    public function colUpdateElements(CustomForm $form, CustomForm $newForm): void
    {
        /** @psalm-suppress InvalidArgument */
        $this->colUpdate(
            oldCollection: $form->getElements(),
            newCollection: $newForm->getElements(),
            updateElementFn: function (CustomFormElement $old, CustomFormElement $new): bool {
                $this->updateElement($old, $new);

                return true;
            },
            addElementFn: function (Collection $oldCollection, CustomFormElement $add) use ($form): bool {
                $this->createElement($form, $add);
                $oldCollection->add($add);

                return true;
            },
            removeElementFn: function (Collection $oldCollection, CustomFormElement $del): bool {
                $oldCollection->removeElement($del);
                $this->entityManager->remove($del);

                return true;
            }
        );
    }

    public function removeElements(CustomForm $form): void
    {
        foreach ($form->getElements() as $element) {
            $this->entityManager->remove($element);
        }
    }

    private function createElement(CustomForm $form, CustomFormElement $element): void
    {
        $this->trackCreation($element);
        $element->setForm($form);
        $this->entityManager->persist($element);
    }

    private function updateElement(CustomFormElement $old, CustomFormElement $new): void
    {
        $this->trackModification($old);
        $old
            ->setName($new->getName())
            ->setProperty($new->getProperty())
            ->setAttributes($new->getAttributes())
            ->setPosition($new->getPosition());
    }
}
