<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;

/**
 * Complete CustomForm processing.
 */
final class CustomFormFacade
{
    use ValidatorAwareTrait;

    public function __construct(
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
