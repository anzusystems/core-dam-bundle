<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetCopyEqualExtSystemValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if ($value instanceof ImageCopyDto) {
            $this->validateImageCopyDto($value);

            return;
        }

        if ($value instanceof JobImageCopyRequestDto) {
            $this->validateImageFileCopyDto($value);

            return;
        }

        throw new UnexpectedTypeException($value, ImageCopyDto::class);
    }

    private function validateImageCopyDto(ImageCopyDto $value): void
    {
        if (null === $value->getAsset()->getId() || null === $value->getTargetAssetLicence()->getId()) {
            return;
        }

        if ($value->getAsset()->getLicence()->getExtSystem()->isNot($value->getTargetAssetLicence()->getExtSystem())) {
            $this->context
                ->buildViolation(ValidationException::ERROR_INVALID_LICENCE)
                ->atPath('targetAssetLicence')
                ->addViolation();
        }
    }

    private function validateImageFileCopyDto(JobImageCopyRequestDto $value): void
    {
        if (null === $value->getTargetAssetLicence()->getId()) {
            return;
        }

        foreach ($value->getItems() as $item) {
            if (null === $item->getImageFile()->getId()) {
                continue;
            }

            if ($item->getImageFile()->getExtSystem()->isNot($value->getTargetAssetLicence()->getExtSystem())) {
                $this->context
                    ->buildViolation(ValidationException::ERROR_INVALID_LICENCE)
                    ->atPath('targetAssetLicence')
                    ->addViolation();

                break;
            }
        }
    }
}
