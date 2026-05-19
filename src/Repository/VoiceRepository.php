<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;

/**
 * @extends AbstractAnzuRepository<Voice>
 *
 * @method Voice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Voice|null findOneBy(array $criteria, array $orderBy = null)
 */
final class VoiceRepository extends AbstractAnzuRepository
{
    public function findOneActiveByFamilyAndProvider(VoiceFamily $family, TtsProvider $provider): ?Voice
    {
        return $this->findOneBy([
            'voiceFamily' => $family,
            'provider' => $provider,
            'active' => true,
        ]);
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
