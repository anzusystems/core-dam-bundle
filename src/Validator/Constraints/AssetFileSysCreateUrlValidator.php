<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetCustomFormProvidableDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetFileSysCreateUrlValidator extends CustomDataValidator
{
    /**
     * @throws NonUniqueResultException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetFileSysUrlCreateDto)) {
            throw new UnexpectedTypeException($constraint, AssetFileSysUrlCreateDto::class);
        }

        $this->validateForm(
            form: $this->customFormProvider->provideForm(
                (new AssetCustomFormProvidableDto(
                    assetType: $value->getAssetType(),
                    extSystem: $value->getExtSystem()
                ))
            ),
            value: $value
        );
    }
}
