<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PodcastConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider
    ) {
    }

    /**
     * @param PodcastConstraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof Podcast)) {
            throw new UnexpectedTypeException($constraint, Podcast::class);
        }

        $configuration = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetType(
            assetType: AssetType::Audio,
            extSystemSlug: $value->getLicence()->getExtSystem()->getSlug()
        );

        if (false === in_array($value->getAttributes()->getFileSlot(), $configuration->getSlots()->getSlots(), true)) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('attributes.fileSlot')
                ->addViolation();
        }
    }
}
