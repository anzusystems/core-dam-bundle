<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

/**
 * Complete CustomForm processing.
 */
final class CustomFormFacade
{
    public function __construct(
        private readonly EntityValidator $validator,
        private readonly CustomFormManager $manager,
    ) {
    }

    /**
     * Process updating of CustomForm.
     *
     * @throws ValidationException
     */
    public function update(AssetCustomForm $customForm, AssetCustomForm $newCustomForm): AssetCustomForm
    {
        $this->validator->validate($newCustomForm, $customForm);
        $this->manager->update($customForm, $newCustomForm);

        return $customForm;
    }

    /**
     * Process deletion.
     */
    public function delete(AssetCustomForm $customForm): void
    {
        $this->manager->delete($customForm);
    }
}
