<?php

namespace AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase;

use AnzuSystems\CoreDamBundle\Entity\Author;
use Doctrine\Common\Collections\Collection;

final readonly class ProcessStringDto
{
    /**
     * @param Collection<string, Author> $authors
     */
    public function __construct(
        private  string $string,
        private array $authorNames,
        private Collection $authors,
    ) {
    }

    public function getAuthorNames(): array
    {
        return $this->authorNames;
    }

    /**
     * @return Collection<string, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function getString(): string
    {
        return $this->string;
    }
}