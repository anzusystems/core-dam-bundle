<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait UuidIdentityDtoTrait
{
    #[Serialize]
    protected string $id = '';

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }
}
