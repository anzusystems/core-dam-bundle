<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ResourceLockerAwareTrait;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Exception\AuthorExistsException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Throwable;

final class AuthorFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;
    use ResourceLockerAwareTrait;

    private const string LOCK_PREFIX = 'author_create_';

    public function __construct(
        private readonly AuthorManager $authorManager,
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws AuthorExistsException
     */
    public function create(Author $author): Author
    {
        // Lowercased: the DB unique check is collation case-insensitive, the Redis lock key must match that.
        $lockName = self::LOCK_PREFIX . mb_strtolower($author->getName()) . '_' . (string) $author->getExtSystem()->getId();
        $this->resourceLocker->lock($lockName);

        try {
            $existingAuthor = $this->authorRepository->findOneByNameAndExtSystem($author->getName(), $author->getExtSystem());
            if ($existingAuthor) {
                throw new AuthorExistsException($existingAuthor);
            }
            $this->validator->validate($author);

            try {
                $this->authorManager->beginTransaction();
                $this->authorManager->create($author);
                $this->indexManager->index($author);
                $this->authorManager->commit();
            } catch (Throwable $exception) {
                if ($this->authorManager->isTransactionActive()) {
                    $this->authorManager->rollback();
                }

                throw new RuntimeException('author_create_failed', 0, $exception);
            }

            return $author;
        } finally {
            $this->resourceLocker->unLock($lockName);
        }
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
            if ($this->authorManager->isTransactionActive()) {
                $this->authorManager->rollback();
            }

            throw new RuntimeException('author_update_failed', 0, $exception);
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
            if ($this->authorManager->isTransactionActive()) {
                $this->authorManager->rollback();
            }

            throw new RuntimeException('author_delete_failed', 0, $exception);
        }

        return true;
    }
}
