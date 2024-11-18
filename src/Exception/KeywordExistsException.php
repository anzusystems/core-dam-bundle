<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Entity\Keyword;
use Exception;

class KeywordExistsException extends Exception
{
    private const string ERROR_MESSAGE = 'keyword_exists_exception';

    public function __construct(
        private readonly Keyword $existingKeyword
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getExistingKeyword(): Keyword
    {
        return $this->existingKeyword;
    }
}
