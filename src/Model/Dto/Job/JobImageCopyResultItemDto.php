<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Job;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class JobImageCopyResultItemDto
{
    #[Assert\Uuid]
    #[Serialize]
    private Uuid $sourceImageId;

    #[Assert\Uuid]
    #[Serialize]
    private Uuid $targetImageId;

    public function __construct()
    {
        $this->setSourceImageId(new NilUuid());
        $this->setTargetImageId(new NilUuid());
    }

    public function getSourceImageId(): Uuid
    {
        return $this->sourceImageId;
    }

    public function setSourceImageId(Uuid $sourceImageId): self
    {
        $this->sourceImageId = $sourceImageId;

        return $this;
    }

    public function getTargetImageId(): Uuid
    {
        return $this->targetImageId;
    }

    public function setTargetImageId(Uuid $targetImageId): self
    {
        $this->targetImageId = $targetImageId;

        return $this;
    }
}
