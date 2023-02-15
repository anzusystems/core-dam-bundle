<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

abstract class AbstractSearchDto implements SearchDtoInterface
{
    #[Serialize]
    protected string $id = '';

    #[Serialize]
    protected string $notId = '';

    #[Serialize]
    protected int $limit = 20;

    #[Serialize]
    protected int $offset = 0;

    #[Serialize]
    protected array $order = [];

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNotId(): string
    {
        return $this->notId;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function getOrder(): array
    {
        return $this->order;
    }

    public function setOrder(array $order): static
    {
        $this->order = $order;

        return $this;
    }
}
