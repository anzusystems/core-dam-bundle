<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use Doctrine\Common\Collections\Collection;

final class AuthorManager extends AbstractManager
{
    public function create(Author $author, bool $flush = true): Author
    {
        $this->trackCreation($author);
        $this->entityManager->persist($author);

        foreach ($author->getCurrentAuthors() as $currentAuthor) {
            $currentAuthor->addChildAuthor($author);
        }
        $author->getFlags()->setCanBeCurrentAuthor($author->getCurrentAuthors()->isEmpty());
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
        $author->getFlags()->setCanBeCurrentAuthor($author->getCurrentAuthors()->isEmpty());

        $this->flush($flush);

        return $author;
    }

    /**
     * The variants get the target authors as their current authors. Reviewed is kept only at the root, so it moves
     * from the variants to the targets when any variant was reviewed.
     *
     * @param list<Author> $variants
     * @param list<Author> $targets
     */
    public function merge(array $variants, array $targets, bool $flush = true): void
    {
        $approveTargets = array_any($variants, static fn (Author $variant): bool => $variant->getFlags()->isReviewed());
        foreach ($variants as $variant) {
            $this->trackModification($variant);
            foreach ($targets as $target) {
                $variant->addCurrentAuthor($target);
                $target->addChildAuthor($variant);
            }
            $variant->getFlags()
                ->setReviewed(false)
                ->setCanBeCurrentAuthor(false);
        }
        foreach ($targets as $target) {
            if ($approveTargets && false === $target->getFlags()->isReviewed()) {
                $this->trackModification($target);
                $target->getFlags()->setReviewed(true);
            }
        }

        $this->flush($flush);
    }

    public function delete(Author $author, bool $flush = true): bool
    {
        $this->entityManager->remove($author);
        $this->flush($flush);

        return true;
    }
}
