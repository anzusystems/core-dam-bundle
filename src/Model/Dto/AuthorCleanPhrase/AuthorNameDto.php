<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AuthorNameDto
{
    #[Serialize]
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
