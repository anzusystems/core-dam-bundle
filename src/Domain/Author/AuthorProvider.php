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

    /**
     * Null when nothing matches or the matches lead to different authors with not exactly one of them reviewed:
     * such data waits for the clean-up, nothing is merged by a guess.
     */
    public function resolve(string $name, ExtSystem $extSystem, string $identifier = App::EMPTY_STRING): ?Author
    {
        $identifierFilter = self::toIdentifierFilter($identifier);
        $exactMatches = $this->repository->findByNameAndExtSystem($name, $extSystem, $identifierFilter);
        if ([] !== $exactMatches) {
            return $this->chooseMatch($exactMatches);
        }

        return $this->chooseMatch($this->repository->findByNormalizedNameAndExtSystem(
            nameNormalized: AuthorNameNormalizer::normalize($name),
            extSystem: $extSystem,
            identifier: $identifierFilter,
        ));
    }

    /**
     * For callers that create the author otherwise: the unique key forbids a second author with an existing name,
     * so an undecided exact match falls back to the first one, reviewed first.
     */
    public function findExisting(string $name, ExtSystem $extSystem, string $identifier = App::EMPTY_STRING): ?Author
    {
        return $this->resolve($name, $extSystem, $identifier)
            ?? $this->repository->findByNameAndExtSystem($name, $extSystem, self::toIdentifierFilter($identifier))[App::ZERO]
            ?? null;
    }

    public function provideByTitle(string $title, ExtSystem $extSystem, bool $flush = true): ?Author
    {
        $title = StringHelper::parseString(input: $title, length: Author::NAME_MAX_LENGTH);
        if (empty($title)) {
            return null;
        }

        $author = $this->findExisting($title, $extSystem);
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
     * A match leads to itself, or to all its current authors, never to a part of them.
     *
     * @param list<Author> $matches
     */
    private function chooseMatch(array $matches): ?Author
    {
        $matchByTarget = [];
        foreach ($matches as $match) {
            $targetKey = $this->getTargetKey($match);
            $isRoot = $match->getCurrentAuthors()->isEmpty();
            if ($isRoot || false === isset($matchByTarget[$targetKey])) {
                $matchByTarget[$targetKey] = $match;
            }
        }

        if (1 === count($matchByTarget)) {
            return array_values($matchByTarget)[App::ZERO];
        }

        $reviewed = array_filter($matchByTarget, $this->isTargetReviewed(...));

        return 1 === count($reviewed) ? array_values($reviewed)[App::ZERO] : null;
    }

    /**
     * @return list<Author>
     */
    private function getTargets(Author $match): array
    {
        return $match->getCurrentAuthors()->isEmpty() ? [$match] : $match->getCurrentAuthors()->getValues();
    }

    private function getTargetKey(Author $match): string
    {
        $targetIds = array_map(static fn (Author $target): string => (string) $target->getId(), $this->getTargets($match));
        sort($targetIds);

        return implode(',', $targetIds);
    }

    private function isTargetReviewed(Author $match): bool
    {
        foreach ($this->getTargets($match) as $target) {
            if (false === $target->getFlags()->isReviewed()) {
                return false;
            }
        }

        return true;
    }

    private static function toIdentifierFilter(string $identifier): ?string
    {
        return App::EMPTY_STRING === $identifier ? null : $identifier;
    }
}
