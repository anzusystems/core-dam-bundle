<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Validator\ElementValidatorInterface;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CustomDataValidator extends ConstraintValidator
{
    private const PATH_TEMPLATE = 'customData.%s';

    private readonly iterable $validators;

    public function __construct(
        private readonly CustomFormProvider $customFormProvider,
        #[TaggedIterator(tag: ElementValidatorInterface::class, indexAttribute: 'key')]
        iterable $validators,
    ) {
        $this->validators = $validators;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function validate($value, Constraint $constraint): void
    {
        if (false === ($value instanceof ResourceCustomFormProvidableInterface) &&
            false === ($value instanceof AssetCustomFormProvidableInterface)
        ) {
            throw new UnexpectedTypeException($constraint, ResourceCustomFormProvidableInterface::class);
        }

        if (false === ($value instanceof CustomDataInterface)) {
            throw new UnexpectedTypeException($constraint, ResourceCustomFormProvidableInterface::class);
        }

        $form = $this->getForm($value);
        $customData = $value->getCustomData();

        foreach ($form->getElements() as $element) {
            $validator = $this->getValidator($element);
            $path = $this->getPath($element->getKey());
            $validator->validate(
                element: $element,
                context: $this->context,
                path: $path,
                value: $customData[$element->getKey()] ?? null
            );

            unset($customData[$element->getKey()]);
        }

        foreach ($customData as $key => $formValue) {
            $this->context->buildViolation(ValidationException::ERROR_INVALID_KEY)
                ->atPath($this->getPath($key))
                ->addViolation();
        }
    }

    private function getPath(string $key): string
    {
        return sprintf(self::PATH_TEMPLATE, $key);
    }

    /**
     * @throws NonUniqueResultException
     */
    private function getForm(ResourceCustomFormProvidableInterface|AssetCustomFormProvidableInterface $formProvidable): CustomForm
    {
        if ($formProvidable instanceof AssetCustomFormProvidableInterface) {
            return $this->customFormProvider->provideFormByAssetProvidable($formProvidable);
        }

        return $this->customFormProvider->provideFormByResourceProvidable($formProvidable);
    }

    /**
     * @throws DomainException
     */
    private function getValidator(CustomFormElement $customFormElement): ElementValidatorInterface
    {
        foreach ($this->validators as $key => $validator) {
            if ($customFormElement->getAttributes()->getType()->toString() === $key) {
                return $validator;
            }
        }

        throw new DomainException(sprintf(
            'Validator for type (%s) is missing',
            $customFormElement->getAttributes()->getType()->toString()
        ));
    }
}
