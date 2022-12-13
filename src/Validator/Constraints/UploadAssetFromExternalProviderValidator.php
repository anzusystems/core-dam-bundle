<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UploadAssetFromExternalProviderValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (false === ($value instanceof UploadAssetFromExternalProviderDto)) {
            throw new UnexpectedTypeException($constraint, UploadAssetFromExternalProviderDto::class);
        }

        $existingAssetFile = $this->assetFileRepository->findOneByOriginExternalProviderAndLicence(
            originExternalProvider: new OriginExternalProvider($value->getExternalProvider(), $value->getId()),
            assetLicence: $value->getAssetLicence(),
        );
        if ($existingAssetFile instanceof AssetFile) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_UNIQUE)
                ->atPath('id')
                ->addViolation();
        }
    }
}
