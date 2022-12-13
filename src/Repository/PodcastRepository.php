<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<Podcast>
 *
 * @method Podcast|null find($id, $lockMode = null, $lockVersion = null)
 * @method Podcast|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PodcastRepository extends AbstractAnzuRepository
{
    public function findAllToImport(): Collection
    {
        return new ArrayCollection(
            $this->findBy(
                [
                    'attributes.mode' => array_map(
                        fn (PodcastImportMode $mode): string => $mode->toString(),
                        PodcastImportMode::getAllImportModes()
                    ),
                ]
            )
        );
    }

    protected function getEntityClass(): string
    {
        return Podcast::class;
    }
}
