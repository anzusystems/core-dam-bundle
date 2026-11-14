<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetFileSysCreateUrlValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AuthorProvider $authorProvider,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetFileSysUrlCreateDto)) {
            throw new UnexpectedTypeException($constraint, AssetFileSysUrlCreateDto::class);
        }

        if (false === $value->hasLicence()) {
            return;
        }

        $this->validateSources($value);
    }

    private function validateSources(AssetFileSysUrlCreateDto $dto): void
    {
        if ([] === $dto->getSources()) {
            return;
        }

        $extSystem = $dto->getExtSystem();
        $enabled = $this->extSystemConfigurationProvider
            ->getExtSystemConfigurationByAssetType($dto->getAssetType(), $extSystem->getSlug())
            ->getSources()
            ->isEnabled();

        /** @var mixed $name */
        foreach ($dto->getSources() as $name) {
            if (false === $enabled || false === is_string($name) || null === $this->authorProvider->findSource($name, $extSystem)) {
                $this->context
                    ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('sources')
                    ->addViolation();

                return;
            }
        }
    }
}
