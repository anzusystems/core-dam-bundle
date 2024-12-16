<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\Collection;

final readonly class AuthorCleanResultDto
{
    /**
     * @param Collection<string, Author> $authors
     */
    public function __construct(
        private string $name,
        private array $authorNames,
        private Collection $authors,
    ) {
    }

    #[Serialize]
    public function getAuthorNames(): array
    {
        return $this->authorNames;
    }

    /**
     * @return Collection<string, Author>
     */
    #[Serialize(handler: EntityIdHandler::class)]
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    #[Serialize]
    public function getName(): string
    {
        return $this->name;
    }
}
