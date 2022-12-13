<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @param FormProvidableMetadataBulkUpdateDto $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof FormProvidableMetadataBulkUpdateDto)) {
            throw new UnexpectedTypeException($constraint, FormProvidableMetadataBulkUpdateDto::class);
        }

        if (false === $value->isDescribed()) {
            return;
        }

        $configuration = $this->configurationProvider->getExtSystemConfigurationByAsset($value->getAsset());
        $keywordsConfig = $configuration->getKeywords();
        $authorsConfig = $configuration->getAuthors();

        if (
            $keywordsConfig->isEnabled()
            && $keywordsConfig->isRequired()
            && $value->getKeywords()->isEmpty()
        ) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('keywords')
                ->addViolation();
        }

        if (
            $authorsConfig->isEnabled()
            && $authorsConfig->isRequired()
            && $value->getAuthors()->isEmpty()
        ) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('authors')
                ->addViolation();
        }
    }
}
