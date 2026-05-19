<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;

/**
 * @extends AbstractAnzuRepository<Voice>
 *
 * @method Voice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Voice|null findOneBy(array $criteria, array $orderBy = null)
 */
final class VoiceRepository extends AbstractAnzuRepository
{
    public function findOneActiveByFamilyAndDiscriminator(VoiceFamily $family, VoiceDiscriminator $discriminator): ?Voice
    {
        $subclass = VoiceDiscriminator::MAP[$discriminator->value];

        return $this->createQueryBuilder('v')
            ->where('v.voiceFamily = :family')
            ->andWhere('v.active = true')
            ->andWhere(sprintf('v INSTANCE OF %s', $subclass))
            ->setParameter('family', $family)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOnePrimaryActiveByFamily(VoiceFamily $family): ?Voice
    {
        return $this->findOneBy([
            'voiceFamily' => $family,
            'main' => true,
            'active' => true,
        ]);
    }

    /**
     * @return list<Voice>
     */
    public function findAllByFamily(VoiceFamily $family): array
    {
        return $this->findBy(['voiceFamily' => $family], ['active' => 'DESC']);
    }

    protected function getEntityClass(): string
    {
        return Voice::class;
    }
}
