<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AuthorSourceFlagValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof Author)) {
            throw new UnexpectedTypeException($constraint, Author::class);
        }

        $id = (string) $value->getId();
        if (App::EMPTY_STRING === $id) {
            return;
        }

        $stored = $this->authorRepository->find($id);
        if ($stored instanceof Author && $stored->getFlags()->isSource() !== $value->getFlags()->isSource()) {
            $this->addViolation('flags.source');
        }
    }

    private function addViolation(string $path): void
    {
        $this->context
            ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath($path)
            ->addViolation();
    }
}
