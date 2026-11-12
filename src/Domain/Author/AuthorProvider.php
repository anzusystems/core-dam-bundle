<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;

final readonly class AuthorProvider
{
    public function __construct(
        private AuthorRepository $repository,
        private AuthorManager $authorManager,
    ) {
    }

    public function resolve(string $name, ExtSystem $extSystem, string $identifier = App::EMPTY_STRING): ?Author
    {
        $identifierFilter = App::EMPTY_STRING === $identifier ? null : $identifier;
        $author = $this->repository->findOneByNameAndExtSystem($name, $extSystem, $identifierFilter);
        if ($author instanceof Author) {
            return $author;
        }

        return $this->resolveByNormalizedName($name, $extSystem, $identifierFilter);
    }

    public function provideByTitle(string $title, ExtSystem $extSystem, bool $flush = true): ?Author
    {
        $title = StringHelper::parseString(input: $title, length: Author::NAME_MAX_LENGTH);
        if (empty($title)) {
            return null;
        }

        $author = $this->resolve($title, $extSystem);
        if ($author instanceof Author) {
            return $author;
        }

        // No ES indexing here — the caller bulk-indexes the created keywords/authors one level up
        // ({@see \AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade}, TtsRequestOrchestrator).
        return $this->authorManager->create(
            author: (new Author())
                ->setExtSystem($extSystem)
                ->setName($title),
            flush: $flush,
        );
    }

    public function provideCurrentAuthorToColl(Asset $asset): bool
    {
        $changedCurrentAuthors = false;
        foreach ($asset->getAuthors()->toArray() as $assetAuthor) {
            if ($assetAuthor->getCurrentAuthors()->isEmpty()) {
                continue;
            }

            $changedCurrentAuthors = true;

            foreach ($assetAuthor->getCurrentAuthors() as $currentAuthor) {
                $asset->addAuthor($currentAuthor);
            }

            $asset->getAuthors()->removeElement($assetAuthor);
        }

        return $changedCurrentAuthors;
    }

    /**
     * Null when no safe single author matches: ambiguous matches are left for the caller to handle as no match.
     */
    private function resolveByNormalizedName(string $name, ExtSystem $extSystem, ?string $identifier): ?Author
    {
        $candidates = $this->repository->findByNormalizedNameAndExtSystem(
            nameNormalized: AuthorNameNormalizer::normalize($name),
            extSystem: $extSystem,
            identifier: $identifier,
        );

        $resolved = [];
        foreach ($candidates as $candidate) {
            $targets = $candidate->getCurrentAuthors()->isEmpty() ? [$candidate] : $candidate->getCurrentAuthors();
            foreach ($targets as $target) {
                $resolved[(string) $target->getId()] = $target;
            }
        }

        if (1 === count($resolved)) {
            return array_values($resolved)[App::ZERO];
        }

        $reviewed = array_filter($resolved, static fn (Author $author): bool => $author->getFlags()->isReviewed());

        return 1 === count($reviewed) ? array_values($reviewed)[App::ZERO] : null;
    }
}
