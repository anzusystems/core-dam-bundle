<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\VideoFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class VideoFileSysUrlCreateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof VideoFileSysUrlCreateDto)) {
            throw new UnexpectedTypeException($constraint, VideoFileSysUrlCreateDto::class);
        }

        if (false === $value->hasLicence()) {
            return;
        }

        if ($this->validateDistributionServices($value)) {
            $this->validateTexts($value);
            $this->validateAuthors($value);
        }
    }

    private function validateDistributionServices(VideoFileSysUrlCreateDto $dto): bool
    {
        $services = $dto->getDistributionServices();
        $configuration = $this->extSystemConfigurationProvider
            ->getAssetConfiguration($dto->getExtSystem()->getSlug(), AssetType::Video);

        /** @var mixed $service */
        foreach ($services as $service) {
            if (
                false === is_string($service) ||
                false === $configuration->getDistribution()->isInDistributionServices($service) ||
                JwDistribution::DISCRIMINATOR !== $this->distributionConfigurationProvider->getDistributionService($service)->getType()
            ) {
                $this->addViolation(ValidationException::ERROR_FIELD_INVALID, 'distributionServices');

                return false;
            }
        }

        return [] !== $services;
    }

    private function validateTexts(VideoFileSysUrlCreateDto $dto): void
    {
        foreach ([StringIndexBuilder::TITLE_KEY, StringIndexBuilder::DESCRIPTION_KEY] as $key) {
            $this->validateText($dto->getCustomData()[$key] ?? null, 'customData.' . $key);
        }
    }

    private function validateText(mixed $text, string $path): void
    {
        if (false === is_string($text)) {
            $this->addViolation(ValidationException::ERROR_FIELD_INVALID, $path);

            return;
        }
        if (App::EMPTY_STRING === trim($text)) {
            $this->addViolation(ValidationException::ERROR_FIELD_EMPTY, $path);
        }
    }

    private function validateAuthors(VideoFileSysUrlCreateDto $dto): void
    {
        $hasAuthor = false;
        foreach ($dto->getAuthors() as $author) {
            if (false === is_string($author)) {
                $this->addViolation(ValidationException::ERROR_FIELD_INVALID, 'authors');

                return;
            }
            if (App::EMPTY_STRING !== trim($author)) {
                $hasAuthor = true;
            }
        }

        if (false === $hasAuthor) {
            $this->addViolation(ValidationException::ERROR_FIELD_EMPTY, 'authors');
        }
    }

    private function addViolation(string $message, string $path): void
    {
        $this->context->buildViolation($message)->atPath($path)->addViolation();
    }
}
