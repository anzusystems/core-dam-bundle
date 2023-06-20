<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints\EntityExists;
use AnzuSystems\CoreDamBundle\Repository\JobPodcastSynchronizerRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobPodcastSynchronizerRepository::class)]
class JobPodcastSynchronizer extends Job
{
    #[ORM\Column(type: Types::STRING, length: 36)]
    #[EntityExists(entity: Podcast::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize]
    private string $podcastId;

    /**
     * Boolean marks podcast synchronizer to handle .
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $fullSync;

    public function __construct()
    {
        parent::__construct();
        $this->setPodcastId('');
        $this->setFullSync(false);
    }

    public function getPodcastId(): string
    {
        return $this->podcastId;
    }

    public function setPodcastId(string $podcastId): self
    {
        $this->podcastId = $podcastId;

        return $this;
    }

    public function isFullSync(): bool
    {
        return $this->fullSync;
    }

    public function setFullSync(bool $fullSync): self
    {
        $this->fullSync = $fullSync;

        return $this;
    }
}
