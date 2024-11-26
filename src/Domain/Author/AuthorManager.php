<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use Doctrine\Common\Collections\Collection;

final class AuthorManager extends AbstractManager
{
    public function create(Author $author, bool $flush = true): Author
    {
        $this->trackCreation($author);
        $this->entityManager->persist($author);

        foreach ($author->getChildAuthors() as $childAuthor) {
            $childAuthor->addCurrentAuthor($author);
        }
        foreach ($author->getCurrentAuthors() as $childAuthor) {
            $childAuthor->addChildAuthor($author);
        }

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

        $this->colUpdate(
            oldCollection: $author->getChildAuthors(),
            newCollection: $newAuthor->getChildAuthors(),
            addElementFn: function (Collection $oldCollection, Author $newChildAuthor) use ($author): bool {
                if (false === $oldCollection->contains($newChildAuthor)) {
                    $oldCollection->add($newChildAuthor);
                }
                $newChildAuthor->addCurrentAuthor($author);

                return true;
            },
            removeElementFn: function (Collection $oldCollection, Author $oldChildAuthor) use ($author): bool {
                if ($oldCollection->contains($oldChildAuthor)) {
                    $oldCollection->removeElement($oldChildAuthor);
                }
                $oldChildAuthor->removeCurrentAuthor($author);

                return true;
            }
        );

        $this->colUpdate(
            oldCollection: $author->getCurrentAuthors(),
            newCollection: $newAuthor->getCurrentAuthors(),
            addElementFn: function (Collection $oldCollection, Author $newCurrentAuthor) use ($author): bool {
                if (false === $oldCollection->contains($newCurrentAuthor)) {
                    $oldCollection->add($newCurrentAuthor);
                }
                $newCurrentAuthor->addChildAuthor($author);

                return true;
            },
            removeElementFn: function (Collection $oldCollection, Author $oldCurrentAuthor) use ($author): bool {
                if ($oldCollection->contains($oldCurrentAuthor)) {
                    $oldCollection->removeElement($oldCurrentAuthor);
                }
                $oldCurrentAuthor->removeChildAuthor($author);

                return true;
            }
        );

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
