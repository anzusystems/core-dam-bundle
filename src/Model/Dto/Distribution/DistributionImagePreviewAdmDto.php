<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionImagePreviewAdmDto extends AbstractEntityDto
{
    private string $service = '';
    private string $url = '';

    public static function getFromDistribution(Distribution $distribution): self
    {
        return static::getBaseInstance($distribution)
            ->setService($distribution->getDistributionService());
    }

    #[Serialize]
    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    #[Serialize]
    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
