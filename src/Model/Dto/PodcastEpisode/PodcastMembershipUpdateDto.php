<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Full desired set of podcasts an asset should belong to (PUT/full-replace semantics).
 */
final class PodcastMembershipUpdateDto
{
    /**
     * @var Collection<int, Podcast>
     */
    #[Serialize(handler: EntityIdHandler::class, type: Podcast::class)]
    #[Assert\Valid]
    private Collection $podcasts;

    public function __construct()
    {
        $this->setPodcasts(new ArrayCollection());
    }

    /**
     * @return Collection<int, Podcast>
     */
    public function getPodcasts(): Collection
    {
        return $this->podcasts;
    }

    /**
     * @param Collection<int, Podcast> $podcasts
     */
    public function setPodcasts(Collection $podcasts): self
    {
        $this->podcasts = $podcasts;

        return $this;
    }
}
