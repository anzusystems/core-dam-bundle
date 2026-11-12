<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizer;

final readonly class AuthorMergeCandidateDto
{
    /**
     * The identifier a merge group is split by: an empty one or one equal to the name counts as none.
     */
    public string $groupIdentifier;

    /**
     * @param list<string> $currentAuthorIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $nameNormalized,
        public string $identifier,
        public bool $reviewed,
        public int $assetCount,
        public string $createdAt,
        public string $createdBy,
        public array $currentAuthorIds,
        public int $childAuthorCount,
    ) {
        $identifierNormalized = AuthorNameNormalizer::normalize($identifier);
        $this->groupIdentifier = $identifierNormalized === $nameNormalized ? App::EMPTY_STRING : $identifierNormalized;
    }
}
