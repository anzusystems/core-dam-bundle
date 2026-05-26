<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;

/**
 * @extends AbstractAnzuRepository<VoiceFamily>
 *
 * @method VoiceFamily|null find($id, $lockMode = null, $lockVersion = null)
 * @method VoiceFamily|null findOneBy(array $criteria, array $orderBy = null)
 */
final class VoiceFamilyRepository extends AbstractAnzuRepository
{
    public function findOneBySlug(string $slug, ExtSystem $extSystem): ?VoiceFamily
    {
        return $this->findOneBy([
            'slug' => $slug,
            'extSystem' => $extSystem,
        ]);
    }

    /**
     * @return VoiceFamily[]
     */
    public function findFamiliesWithoutBindingFor(VoiceDiscriminator $discriminator): array
    {
        return $this->createQueryBuilder('vf')
            ->andWhere(
                'NOT EXISTS (SELECT 1 FROM ' . Voice::class . ' v WHERE v.voiceFamily = vf AND v INSTANCE OF :discriminator)',
            )
            ->setParameter('discriminator', $discriminator->value)
            ->orderBy('vf.extSystem', 'ASC')
            ->addOrderBy('vf.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    protected function getEntityClass(): string
    {
        return VoiceFamily::class;
    }
}
