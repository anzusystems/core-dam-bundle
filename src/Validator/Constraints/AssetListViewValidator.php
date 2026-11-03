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

        $invalidPath = $this->findInvalidPath($value);
        if (null !== $invalidPath) {
            $this->addViolation($invalidPath);
        }
    }

    private function findInvalidPath(EntityAssetListView $view): ?string
    {
        $extSystem = $view->getExtSystem();

        foreach ($view->getGroups() as $group) {
            if ($group->getExtSystem()->isNot($extSystem)) {
                return 'groups';
            }
        }

        $licenceIds = [];
        foreach ($view->getLicences() as $licence) {
            if ($licence->getExtSystem()->isNot($extSystem)) {
                return 'licences';
            }
            $licenceIds[(int) $licence->getId()] = true;
        }

        $uploadLicence = $view->getUploadLicence();
        if (null !== $uploadLicence && false === isset($licenceIds[(int) $uploadLicence->getId()])) {
            return 'uploadLicence';
        }

        if ($view->getGroups()->isEmpty()) {
            return null;
        }

        $reachableLicenceIds = [];
        foreach ($view->getGroups() as $group) {
            foreach ($group->getLicences() as $licence) {
                $reachableLicenceIds[(int) $licence->getId()] = true;
            }
        }

        return [] === array_diff_key($licenceIds, $reachableLicenceIds) ? null : 'licences';
    }

    private function addViolation(string $path): void
    {
        $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath($path)
            ->addViolation()
        ;
    }
}
