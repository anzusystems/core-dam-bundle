<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetCustomFormProvidableDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\VideoFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class VideoFileSysUrlCreateValidator extends ConstraintValidator
{
    private const int TITLE_MAX_LENGTH = 100;
    private const int DESCRIPTION_MAX_LENGTH = 5_000;
    private const string TITLE = 'title';
    private const string DESCRIPTION = 'description';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly CustomFormProvider $customFormProvider,
        private readonly ImageFileRepository $imageFileRepository,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
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
            $this->validateCustomForm($value);
        }

        $this->validateImagePreview($value);
    }

    private function validateDistributionServices(VideoFileSysUrlCreateDto $dto): bool
    {
        $services = $dto->getDistributionServices();
        $allowedServices = $this->extSystemConfigurationProvider
            ->getAssetConfiguration($dto->getExtSystem()->getSlug(), AssetType::Video)
            ->getDistribution()
            ->getDistributionServices();

        foreach ($services as $service) {
            if (
                false === is_string($service) ||
                false === in_array($service, $allowedServices, true) ||
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
        $this->validateText($dto->getCustomData()[self::TITLE] ?? null, self::TITLE_MAX_LENGTH);
        $this->validateText($dto->getCustomData()[self::DESCRIPTION] ?? null, self::DESCRIPTION_MAX_LENGTH);
    }

    private function validateText(mixed $text, int $maxLength): void
    {
        if (false === is_string($text)) {
            $this->addViolation(ValidationException::ERROR_FIELD_INVALID, 'customData');

            return;
        }
        if (App::EMPTY_STRING === trim($text)) {
            $this->addViolation(ValidationException::ERROR_FIELD_EMPTY, 'customData');

            return;
        }
        if (mb_strlen($text) > $maxLength) {
            $this->addViolation(ValidationException::ERROR_FIELD_LENGTH_MAX, 'customData');
        }
    }

    private function validateAuthors(VideoFileSysUrlCreateDto $dto): void
    {
        foreach ($dto->getAuthors() as $author) {
            if (is_string($author) && App::EMPTY_STRING !== trim($author)) {
                return;
            }
        }

        $this->addViolation(ValidationException::ERROR_FIELD_EMPTY, 'authors');
    }

    /**
     * @throws NonUniqueResultException
     */
    private function validateCustomForm(VideoFileSysUrlCreateDto $dto): void
    {
        $form = $this->customFormProvider->provideForm(
            new AssetCustomFormProvidableDto(AssetType::Video, $dto->getExtSystem())
        );

        $properties = [];
        foreach ($form->getElements() as $element) {
            $properties[] = $element->getProperty();
        }

        if (
            false === in_array(self::TITLE, $properties, true) ||
            false === in_array(self::DESCRIPTION, $properties, true)
        ) {
            $this->addViolation(ValidationException::ERROR_FIELD_INVALID, 'customData');
        }
    }

    private function validateImagePreview(VideoFileSysUrlCreateDto $dto): void
    {
        $imagePreviewId = $dto->getImagePreviewAssetFileId();
        if (null === $imagePreviewId) {
            return;
        }

        if (null === $this->imageFileRepository->findProcessedByIdAndLicence($imagePreviewId, $dto->getLicence())) {
            $this->addViolation(ValidationException::ERROR_FIELD_INVALID, 'imagePreviewAssetFileId');
        }
    }

    private function addViolation(string $message, string $path): void
    {
        $this->context->buildViolation($message)->atPath($path)->addViolation();
    }
}
