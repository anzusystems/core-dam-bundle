<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

abstract class AbstractDistributionData
{
    #[Serialize]
    protected DistributionDataUrl $thumbnail;

    public function __construct()
    {
        $this->setThumbnail(new DistributionDataUrl());
    }

    public function getThumbnail(): DistributionDataUrl
    {
        return $this->thumbnail;
    }

    public function setThumbnail(DistributionDataUrl $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }
}
