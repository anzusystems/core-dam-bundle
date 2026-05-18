<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;

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

    protected function getEntityClass(): string
    {
        return VoiceFamily::class;
    }
}
