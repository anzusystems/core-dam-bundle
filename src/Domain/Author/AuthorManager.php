<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Author;

final class AuthorManager extends AbstractManager
{
    public function create(Author $author, bool $flush = true): Author
    {
        $this->trackCreation($author);
        $this->entityManager->persist($author);
        $this->flush($flush);

        return $author;
    }

    public function update(Author $author, Author $newAuthor, bool $flush = true): Author
    {
        $this->trackModification($author);
        $author
            ->setName($newAuthor->getName())
            ->setIdentifier($newAuthor->getIdentifier())
            ->setFlags($newAuthor->getFlags())
            ->setType($newAuthor->getType())
        ;
        $this->flush($flush);

        return $author;
    }

    public function delete(Author $author, bool $flush = true): bool
    {
        $this->entityManager->remove($author);
        $this->flush($flush);

        return true;
    }
}
