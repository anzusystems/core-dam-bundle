<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<AuthorCleanPhrase>
 *
 * @method AuthorCleanPhrase|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuthorCleanPhrase|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AuthorCleanPhraseRepository extends AbstractAnzuRepository
{
    public function findAllByTypeAndMode(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->where('entity.type = :type')
                ->andWhere('entity.mode = :mode')
                ->setParameter('type', $type->toString())
                ->setParameter('mode', $mode->toString())
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return AuthorCleanPhrase::class;
    }
}
