<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\Author;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AuthorCurrentAuthorValidator extends ConstraintValidator
{
    public function __construct(
    ) {
    }

    /**
     * @param AuthorCurrentAuthor $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof Author)) {
            throw new UnexpectedTypeException($constraint, Author::class);
        }

        if (false === $value->getChildAuthors()->isEmpty() && false === $value->getCurrentAuthors()->isEmpty()) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('currentAuthors')
                ->addViolation();

            return;
        }

        foreach ($value->getCurrentAuthors() as $currentAuthor) {
            if ($currentAuthor->is($value)) {
                $this->context
                    ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('currentAuthors')
                    ->addViolation();

                return;
            }

            if (false === $currentAuthor->getFlags()->isCanBeCurrentAuthor()) {
                $this->context
                    ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('currentAuthors')
                    ->addViolation();

                return;
            }
        }
    }
}
