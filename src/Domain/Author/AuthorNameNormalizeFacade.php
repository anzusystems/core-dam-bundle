<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AuthorDBALRepository;
use Generator;

final readonly class AuthorNameNormalizeFacade
{
    private const int BATCH_SIZE = 1_000;

    public function __construct(
        private AuthorDBALRepository $authorDBALRepository,
    ) {
    }

    public function countAuthors(bool $all): int
    {
        return $this->authorDBALRepository->countForNormalization($all);
    }

    /**
     * Yields once per author: true when its normalized name was written.
     *
     * @return Generator<int, bool>
     */
    public function normalizeNames(bool $all): Generator
    {
        $lastId = App::EMPTY_STRING;
        do {
            $authors = $this->authorDBALRepository->findForNormalization(
                lastId: $lastId,
                limit: self::BATCH_SIZE,
                all: $all,
            );
            foreach ($authors as $author) {
                $lastId = $author->id;
                $nameNormalized = AuthorNameNormalizer::normalize($author->name);
                if ($nameNormalized === $author->nameNormalized) {
                    yield false;

                    continue;
                }

                $this->authorDBALRepository->updateNameNormalized($author->id, $nameNormalized);

                yield true;
            }
        } while (self::BATCH_SIZE === count($authors));
    }
}
