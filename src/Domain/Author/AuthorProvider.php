<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;

final readonly class AuthorProvider
{
    private const string LOCK_PREFIX = 'author_create_';

    public function __construct(
        private AuthorRepository $repository,
        private AuthorManager $authorManager,
        private ResourceLocker $resourceLocker,
    ) {
    }

    public static function createLockName(string $name, ExtSystem $extSystem): string
    {
        // Falls back to the lowercased name: the DB unique check is collation case-insensitive, the lock key must match.
        $key = AuthorNameNormalizer::normalize($name);
        if (App::EMPTY_STRING === $key) {
            $key = mb_strtolower($name);
        }

        return self::LOCK_PREFIX . $key . '_' . (string) $extSystem->getId();
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

    /**
     * Null when no safe single author matches: ambiguous matches are left for the caller to handle as no match.
     */
    public function resolveByNormalizedName(string $name, ExtSystem $extSystem, ?string $identifier = null): ?Author
    {
        $candidates = $this->repository->findByNormalizedNameAndExtSystem(
            AuthorNameNormalizer::normalize($name),
            $extSystem,
            $identifier,
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

        $lockName = self::createLockName($title, $extSystem);
        $this->resourceLocker->lock($lockName);

        try {
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
        } finally {
            $this->resourceLocker->unLock($lockName);
        }
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
}
