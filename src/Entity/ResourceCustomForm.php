<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Repository\ResourceCustomFormRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResourceCustomFormRepository::class)]
#[ORM\Index(fields: ['resourceKey'], name: 'IDX_resource_key')]
class ResourceCustomForm extends CustomForm
{
    #[ORM\Column(type: Types::STRING)]
    private string $resourceKey;

    public function __construct()
    {
        parent::__construct();
    }

    public function getResourceKey(): string
    {
        return $this->resourceKey;
    }

    public function setResourceKey(string $resourceKey): self
    {
        $this->resourceKey = $resourceKey;

        return $this;
    }
}
