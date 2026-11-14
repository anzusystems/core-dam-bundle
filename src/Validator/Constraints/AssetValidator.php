<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeSourcesConfiguration;
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

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof FormProvidableMetadataBulkUpdateDto)) {
            throw new UnexpectedTypeException($constraint, FormProvidableMetadataBulkUpdateDto::class);
        }

        if (
            $value->isAuthorsUndefined() &&
            $value->isSourcesUndefined() &&
            ($value->isDescribedUndefined() || false === $value->isDescribed())
        ) {
            return;
        }

        $configuration = $this->configurationProvider->getExtSystemConfigurationByAsset($value->getAsset());
        $sourcesConfig = $configuration->getSources();

        $this->validateSources($value, $sourcesConfig);
        $this->validateAuthorsHaveNoNewSource($value, $sourcesConfig);

        if ($value->isDescribedUndefined() || false === $value->isDescribed()) {
            return;
        }

        $keywordsConfig = $configuration->getKeywords();
        $authorsConfig = $configuration->getAuthors();

        if (
            false === $value->isKeywordsUndefined() &&
            $keywordsConfig->isEnabled() &&
            $keywordsConfig->isRequired() &&
            $value->getKeywords()->isEmpty()
        ) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('keywords')
                ->addViolation();
        }

        if (
            false === $value->isAuthorsUndefined() &&
            $authorsConfig->isEnabled() &&
            $authorsConfig->isRequired() &&
            $value->getAuthors()->isEmpty()
        ) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('authors')
                ->addViolation();
        }

        if (
            false === $value->isSourcesUndefined() &&
            $sourcesConfig->isRequiredInAdm() &&
            $value->getSources()->isEmpty()
        ) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('sources')
                ->addViolation();
        }
    }

    private function validateSources(
        FormProvidableMetadataBulkUpdateDto $value,
        ExtSystemAssetTypeSourcesConfiguration $sourcesConfig,
    ): void {
        if ($value->isSourcesUndefined() || $value->getSources()->isEmpty()) {
            return;
        }

        if (false === $sourcesConfig->isEnabled()) {
            $this->addInvalid('sources');

            return;
        }

        $extSystemId = $value->getExtSystem()->getId();
        foreach ($value->getSources() as $source) {
            if (false === $source->getFlags()->isSource() || $source->getExtSystem()->getId() !== $extSystemId) {
                $this->addInvalid('sources');

                return;
            }
        }
    }

    private function validateAuthorsHaveNoNewSource(
        FormProvidableMetadataBulkUpdateDto $value,
        ExtSystemAssetTypeSourcesConfiguration $sourcesConfig,
    ): void {
        if ($value->isAuthorsUndefined() || false === $sourcesConfig->isEnabled()) {
            return;
        }

        $storedAuthors = $value->getAsset()->getAuthors();
        foreach ($value->getAuthors() as $author) {
            if ($author->getFlags()->isSource() && false === $storedAuthors->contains($author)) {
                $this->addInvalid('authors');

                return;
            }
        }
    }

    private function addInvalid(string $path): void
    {
        $this->context
            ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath($path)
            ->addViolation();
    }
}
