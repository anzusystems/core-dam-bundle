<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\CustomForm;

/**
 * @extends AbstractAnzuRepository<CustomForm>
 *
 * @method CustomForm|null find($id, $lockMode = null, $lockVersion = null)
 * @method CustomForm|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method CustomForm|null findProcessedById(string $id)
 * @method CustomForm|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class CustomFormRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return CustomForm::class;
    }
}
