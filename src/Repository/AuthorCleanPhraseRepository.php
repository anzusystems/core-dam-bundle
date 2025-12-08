<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;

/**
 * @extends AbstractAnzuRepository<AuthorCleanPhrase>
 *
 * @method AuthorCleanPhrase|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuthorCleanPhrase|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AuthorCleanPhraseRepository extends AbstractAnzuRepository
{
    public function findAllByTypeAndMode(
        AuthorCleanPhraseType $type,
        AuthorCleanPhraseMode $mode,
        ExtSystem $extSystem,
        ?bool $wordBoundary = null
    ): Collection {
        $qb = $this->createQueryBuilder('entity')
            ->where('entity.type = :type')
            ->andWhere('entity.mode = :mode')
            ->andWhere('IDENTITY(entity.extSystem) = :extSystem')
            ->setParameter('type', $type->toString())
            ->setParameter('mode', $mode->toString())
            ->setParameter('extSystem', (int) $extSystem->getId())
            ->orderBy('entity.position', Order::Ascending->value)
        ;

        if (is_bool($wordBoundary)) {
            $qb->andWhere('entity.flags.wordBoundary = :wordBoundary')
                ->setParameter('wordBoundary', $wordBoundary)
            ;
        }

        return new ArrayCollection(
            $qb->getQuery()->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return AuthorCleanPhrase::class;
    }
}
