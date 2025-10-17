<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

interface SearchDtoInterface
{
    public function getId(): string;

    public function getNotId(): string;

    public function getIndexName(): string;

    public function getLimit(): int;

    public function getOffset(): int;

    public function getOrder(): array;

    public function getText(): string;
}
