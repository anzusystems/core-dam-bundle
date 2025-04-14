<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Job;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class JobImageCopyResultDto
{
    #[Serialize]
    private int $id = App::ZERO;

    #[Serialize]
    private int $failedCount = App::ZERO;

    #[Serialize]
    private JobStatus $status = JobStatus::Default;

    #[Serialize(type: JobImageCopyResultItemDto::class)]
    private Collection $items;

    public function __construct()
    {
        $this->setItems(new ArrayCollection());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): JobImageCopyResultDto
    {
        $this->id = $id;
        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): self
    {
        $this->failedCount = $failedCount;
        return $this;
    }

    public function getStatus(): JobStatus
    {
        return $this->status;
    }

    public function setStatus(JobStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, JobImageCopyResultItemDto>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setItems(Collection $items): self
    {
        $this->items = $items;

        return $this;
    }
}
