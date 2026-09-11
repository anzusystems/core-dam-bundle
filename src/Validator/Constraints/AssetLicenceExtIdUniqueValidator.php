<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetLicenceExtIdUniqueValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AssetLicenceRepository $assetLicenceRepository,
    ) {
    }

    /**
     * @param AssetLicenceExtIdUnique $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetLicence)) {
            throw new UnexpectedTypeException($constraint, AssetLicence::class);
        }

        $extId = $value->getExtId();
        if (null === $extId) {
            return;
        }

        $existing = $this->assetLicenceRepository->findOneByExtSystemAndExtId($value->getExtSystem(), $extId);
        if (null === $existing || $existing->getId() === $value->getId()) {
            return;
        }

        $this->context->buildViolation(ValidationException::ERROR_FIELD_UNIQUE)
            ->atPath('extId')
            ->addViolation()
        ;
    }
}
