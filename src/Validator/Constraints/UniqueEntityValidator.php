<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueEntityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param UniqueEntity $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof BaseIdentifiableInterface)) {
            throw new UnexpectedTypeException($constraint, BaseIdentifiableInterface::class);
        }

        $fieldsNames = $constraint->fields;
        $fields = [];
        foreach ($fieldsNames as $fieldName) {
            $fields[$fieldName] = $this->propertyAccessor->getValue($value, $fieldName);
        }

        /** @var IdentifiableInterface $existingEntity */
        $existingEntity = $this->entityManager->getRepository($value::class)->findOneBy($fields);
        if ($existingEntity && (empty($value->getId()) || $existingEntity->isNot($value))) {
            foreach ($constraint->errorAtPath ?: $fieldsNames as $fieldsName) {
                $this->context->buildViolation($constraint->message)->atPath($fieldsName)->addViolation();
            }
        }
    }
}
