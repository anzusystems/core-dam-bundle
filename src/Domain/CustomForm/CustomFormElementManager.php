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
        $this->colUpdate(
            oldCollection: $form->getElements(),
            newCollection: $newForm->getElements(),
            updateElementFn: function (CustomFormElement $old, CustomFormElement $new) {
                $this->updateElement($old, $new);
            },
            addElementFn: function (Collection $oldCollection, CustomFormElement $add) use ($form) {
                $this->createElement($form, $add);
                $oldCollection->add($add);
            },
            removeElementFn: function (Collection $oldCollection, CustomFormElement $del) {
                $oldCollection->removeElement($del);
                $this->entityManager->remove($del);
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
            ->setKey($new->getKey())
            ->setAttributes($new->getAttributes())
            ->setPosition($new->getPosition());
    }
}
