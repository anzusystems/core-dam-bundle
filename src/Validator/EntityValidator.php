<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityValidator
{
    private ConstraintViolationListInterface $violationList;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function validateDto(object $dto, ?BaseIdentifiableInterface $oldEntity = null): void
    {
        $this->violationList = new ConstraintViolationList();
        $this->violationList->addAll(
            $this->validator->validate($dto)
        );
        $this->validateDtoIdentity($dto, $oldEntity);

        if ($this->violationList->count() > 0) {
            throw new ValidationException($this->violationList);
        }
    }

    /**
     * @throws ValidationException
     */
    public function validate(BaseIdentifiableInterface $newEntity, ?BaseIdentifiableInterface $oldEntity = null): void
    {
        $this->violationList = new ConstraintViolationList();
        $this->validateConstraints($newEntity);
        $this->validateIdentity($newEntity, $oldEntity);

        if ($this->violationList->count() > 0) {
            throw new ValidationException($this->violationList);
        }
    }

    public function addIdMismatchException(object $identifiable, mixed $id): void
    {
        $this->violationList->add(
            new ConstraintViolation(
                ValidationException::ERROR_ID_MISMATCH,
                ValidationException::ERROR_ID_MISMATCH,
                [],
                $identifiable::class,
                'id',
                $id,
            )
        );
    }

    private function validateConstraints(BaseIdentifiableInterface $entity): void
    {
        $this->violationList->addAll(
            $this->validator->validate($entity)
        );
    }

    private function validateIdentity(
        BaseIdentifiableInterface $newEntity,
        ?BaseIdentifiableInterface $oldEntity = null
    ): void {
        if (null === $oldEntity || $newEntity->getId() === $oldEntity->getId()) {
            return;
        }

        $this->addIdMismatchException($newEntity, $newEntity->getId());
    }

    private function validateDtoIdentity(
        object $dto,
        ?BaseIdentifiableInterface $oldEntity = null
    ): void {
        if (null === $oldEntity || false === method_exists($dto, 'getId')) {
            return;
        }

        if ($dto->getId() === $oldEntity->getId()) {
            return;
        }

        $this->addIdMismatchException($dto, $dto->getId());
    }
}
