<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata;

use AnzuSystems\CoreDamBundle\Entity\Author;

final readonly class DataSuggestionsDto
{
    /**
     * @param array<string, list<string>> $suggestions tag name => suggested entity ids
     * @param list<Author> $authors authors to add to the asset as they are, without a suggestion
     */
    public function __construct(
        private array $suggestions,
        private array $authors = [],
    ) {
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * @return list<Author>
     */
    public function getAuthors(): array
    {
        return $this->authors;
    }
}
