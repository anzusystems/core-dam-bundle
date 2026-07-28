<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Entity\Author;
use Exception;

class AuthorExistsException extends Exception
{
    private const string ERROR_MESSAGE = 'author_exists_exception';

    public function __construct(
        private readonly Author $existingAuthor
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getExistingAuthor(): Author
    {
        return $this->existingAuthor;
    }
}
