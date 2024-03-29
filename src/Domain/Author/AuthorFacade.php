<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Throwable;

final class AuthorFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly AuthorManager $authorManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(Author $author): Author
    {
        $this->validator->validate($author);

        try {
            $this->authorManager->beginTransaction();
            $this->authorManager->create($author);
            $this->indexManager->index($author);
            $this->authorManager->commit();
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw new RuntimeException('author_create_failed', 0, $exception);
        }

        return $author;
    }

    /**
     * @throws ValidationException
     */
    public function update(Author $author, Author $newAuthor): Author
    {
        $this->validator->validate($newAuthor, $author);

        try {
            $this->authorManager->beginTransaction();
            $this->authorManager->update($author, $newAuthor);
            $this->indexManager->index($author);
            $this->authorManager->commit();
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw new RuntimeException('author_create_failed', 0, $exception);
        }

        return $author;
    }

    public function delete(Author $author): bool
    {
        try {
            $deletedId = (string) $author->getId();
            $this->authorManager->beginTransaction();
            $this->authorManager->delete($author);
            $this->indexManager->delete($author, $deletedId);
            $this->authorManager->commit();
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw new RuntimeException('keyword_delete_failed', 0, $exception);
        }

        return true;
    }
}
