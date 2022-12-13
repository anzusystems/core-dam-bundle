<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use Exception;

/**
 * CustomForm persistence management.
 */
final class CustomFormManager extends AbstractManager
{
    public function __construct(
        private readonly CustomFormElementManager $elementManager,
    ) {
    }

    /**
     * Persist new form.
     *
     * @throws Exception
     */
    public function create(CustomForm $form, bool $flush = true): CustomForm
    {
        $this->trackCreation($form);
        $this->elementManager->createElements($form);
        $this->reorderPositionedColl($form->getElements());
        $this->entityManager->persist($form);
        $this->flush($flush);

        return $form;
    }

    /**
     * Update form with fields from new form and persist it.
     *
     * @throws Exception
     */
    public function update(CustomForm $form, CustomForm $newForm, bool $flush = true): CustomForm
    {
        $this->trackModification($form);
        $this->elementManager->colUpdateElements($form, $newForm);
        $this->reorderPositionedColl($form->getElements());
        $this->flush($flush);

        return $form;
    }

    /**
     * Delete form from persistence.
     */
    public function delete(CustomForm $form, bool $flush = true): void
    {
        $this->elementManager->removeElements($form);
        $this->entityManager->remove($form);
        $this->flush($flush);
    }
}
