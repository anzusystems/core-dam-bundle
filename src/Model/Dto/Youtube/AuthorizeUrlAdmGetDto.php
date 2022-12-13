<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AuthorizeUrlAdmGetDto
{
    #[Serialize]
    private string $url;

    public static function getInstance(string $url): self
    {
        return (new self())
            ->setUrl($url);
    }

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
