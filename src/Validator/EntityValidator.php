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
    public function validateDto(object $dto): void
    {
        $this->violationList = new ConstraintViolationList();
        $this->violationList->addAll(
            $this->validator->validate($dto)
        );

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

        $this->violationList->add(
            new ConstraintViolation(
                ValidationException::ERROR_ID_MISMATCH,
                ValidationException::ERROR_ID_MISMATCH,
                [],
                $newEntity::class,
                'id',
                $newEntity->getId(),
            )
        );
    }
}
