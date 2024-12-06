<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

/**
 * @extends AbstractAnzuRepository<AuthorCleanPhrase>
 *
 * @method AuthorCleanPhrase|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuthorCleanPhrase|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AuthorCleanPhraseRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return AuthorCleanPhrase::class;
    }
}
