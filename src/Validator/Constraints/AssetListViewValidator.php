<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetListView as EntityAssetListView;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetListViewValidator extends ConstraintValidator
{
    /**
     * @param AssetListView $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof EntityAssetListView)) {
            throw new UnexpectedTypeException($constraint, EntityAssetListView::class);
        }

        $extSystemId = $value->getExtSystem()->getId();

        foreach ($value->getGroups() as $group) {
            if ($group->getExtSystem()->getId() !== $extSystemId) {
                $this->addViolation('groups');

                return;
            }
        }

        foreach ($value->getLicences() as $licence) {
            if ($licence->getExtSystem()->getId() !== $extSystemId) {
                $this->addViolation('licences');

                return;
            }
        }

        if ($value->getGroups()->isEmpty()) {
            return;
        }

        $reachableLicenceIds = [];
        foreach ($value->getGroups() as $group) {
            foreach ($group->getLicences() as $licence) {
                $reachableLicenceIds[(int) $licence->getId()] = true;
            }
        }

        foreach ($value->getLicences() as $licence) {
            if (false === isset($reachableLicenceIds[(int) $licence->getId()])) {
                $this->addViolation('licences');

                return;
            }
        }
    }

    private function addViolation(string $path): void
    {
        $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath($path)
            ->addViolation()
        ;
    }
}
