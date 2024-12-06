<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;

final class AuthorCleanPhraseManager extends AbstractManager
{
    public function create(AuthorCleanPhrase $bannedPhrase, bool $flush = true): AuthorCleanPhrase
    {
        $this->trackCreation($bannedPhrase);
        $this->entityManager->persist($bannedPhrase);
        $this->flush($flush);

        return $bannedPhrase;
    }

    public function update(AuthorCleanPhrase $bannedPhrase, AuthorCleanPhrase $newBannedPhrase, bool $flush = true): AuthorCleanPhrase
    {
        $bannedPhrase
            ->setPhrase($newBannedPhrase->getPhrase())
            ->setType($newBannedPhrase->getType())
        ;
        $this->trackModification($bannedPhrase);
        $this->flush($flush);

        return $bannedPhrase;
    }

    /**
     * Delete authorCleanPhrase from persistence.
     */
    public function delete(AuthorCleanPhrase $bannedPhrase, bool $flush = true): bool
    {
        $this->entityManager->remove($bannedPhrase);
        $this->flush($flush);

        return true;
    }
}
