<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetCustomFormProvidableDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\Service\Attribute\Required;

final class AssetFileSysCreatePathValidator extends CustomDataValidator
{
    private ExtSystemConfigurationProvider $configurationProvider;
    private AssetFileFactory $assetFileFactory;

    #[Required]
    public function setConfigurationProvider(ExtSystemConfigurationProvider $configurationProvider): void
    {
        $this->configurationProvider = $configurationProvider;
    }

    #[Required]
    public function setAssetFileFactory(AssetFileFactory $assetFileFactory): void
    {
        $this->assetFileFactory = $assetFileFactory;
    }

    /**
     * @throws InvalidMimeTypeException
     * @throws FilesystemException
     * @throws NonUniqueResultException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetFileSysPathCreateDto)) {
            throw new UnexpectedTypeException($constraint, AssetFileSysPathCreateDto::class);
        }

        $configuration = $this->configurationProvider->getExtSystemConfiguration($value->getExtSystem()->getSlug());

        try {
            $assetType = $this->assetFileFactory->getTypeFromPath($configuration->getExtStorage(), $value->getPath());
        } catch (DomainException) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('path')
                ->addViolation();

            return;
        }

        $this->validateForm(
            form: $this->customFormProvider->provideForm(
                (new AssetCustomFormProvidableDto(
                    assetType: $assetType,
                    extSystem: $value->getExtSystem()
                ))
            ),
            value: $value
        );
    }
}
