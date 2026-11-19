<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeSourcesConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use Closure;
use Doctrine\Common\Collections\Collection;

final readonly class AuthorProvider
{
    public function __construct(
        private AuthorRepository $repository,
        private AuthorManager $authorManager,
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private DamLogger $damLogger,
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

    public function findSource(string $name, ExtSystem $extSystem): ?Author
    {
        $author = $this->resolve($name, $extSystem);

        return $author?->getFlags()->isSource() ? $author : null;
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

    /**
     * Null when the asset type of the ext system has no default source.
     *
     * @throws RuntimeException when the configured default source does not exist or is not a source
     */
    public function getDefaultSource(ExtSystem $extSystem, AssetType $assetType): ?Author
    {
        $defaultSourceId = $this->getSourcesConfiguration($extSystem, $assetType)->getDefaultSourceId();
        if (null === $defaultSourceId) {
            return null;
        }

        $source = $this->repository->find($defaultSourceId);
        if ($source instanceof Author && $source->getFlags()->isSource() && $source->getExtSystem()->is($extSystem)) {
            return $source;
        }

        throw new RuntimeException('default_source_not_source');
    }

    /**
     * A wrong default source must not stop the sys API: the asset stays without a source and the error is logged.
     */
    public function provideDefaultSource(Asset $asset): void
    {
        if (false === $asset->getSources()->isEmpty()) {
            return;
        }

        try {
            $source = $this->getDefaultSource($asset->getExtSystem(), $asset->getAttributes()->getAssetType());
        } catch (RuntimeException $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_PROCESS,
                sprintf('Default source of ext system (%s) is not set right', $asset->getExtSystem()->getSlug()),
                exception: $exception,
            );

            return;
        }

        if ($source instanceof Author) {
            $asset->addSource($source);
        }
    }

    /**
     * Adds the author and resolves aliases in the same step, so the asset never carries an alias
     * next to the current author it stands for. A source lands in `sources` when the asset type has them enabled.
     * Returns whether the author was newly added.
     */
    public function provideAuthorToColl(Asset $asset, Author $author): bool
    {
        if ($asset->getAuthors()->contains($author) || $asset->getSources()->contains($author)) {
            return false;
        }

        $asset->addAuthor($author);
        $this->provideCurrentAuthorToColl($asset);

        return true;
    }

    public function provideCurrentAuthorToColl(Asset $asset): bool
    {
        $changed = $this->replaceByCurrentAuthors($asset->getAuthors(), $asset->addAuthor(...));
        $changed = $this->replaceByCurrentAuthors($asset->getSources(), $asset->addSource(...)) || $changed;

        $sourcesConfig = $this->getSourcesConfiguration($asset->getExtSystem(), $asset->getAttributes()->getAssetType());
        if ($sourcesConfig->isEnabled()) {
            $changed = $this->routeBySourceFlag($asset) || $changed;
            $changed = $this->removeDefaultSourceBesideSource($asset, $sourcesConfig->getDefaultSourceId()) || $changed;
        }

        return $changed;
    }

    private function getSourcesConfiguration(ExtSystem $extSystem, AssetType $assetType): ExtSystemAssetTypeSourcesConfiguration
    {
        return $this->extSystemConfigurationProvider
            ->getExtSystemConfigurationByAssetType($assetType, $extSystem->getSlug())
            ->getSources();
    }

    /**
     * @param Collection<string, Author> $collection
     * @param Closure(Author): mixed $add
     */
    private function replaceByCurrentAuthors(Collection $collection, Closure $add): bool
    {
        $changed = false;
        foreach ($collection->toArray() as $author) {
            if ($author->getCurrentAuthors()->isEmpty()) {
                continue;
            }

            $changed = true;

            foreach ($author->getCurrentAuthors() as $currentAuthor) {
                $add($currentAuthor);
            }

            $collection->removeElement($author);
        }

        return $changed;
    }

    private function routeBySourceFlag(Asset $asset): bool
    {
        $changed = false;
        foreach ($asset->getAuthors()->toArray() as $author) {
            if ($author->getFlags()->isSource()) {
                $asset->addSource($author);
                $asset->getAuthors()->removeElement($author);
                $changed = true;
            }
        }

        foreach ($asset->getSources()->toArray() as $source) {
            if (false === $source->getFlags()->isSource()) {
                $asset->addAuthor($source);
                $asset->getSources()->removeElement($source);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * The default source means the asset has no real source, so it gives way to any other source.
     */
    private function removeDefaultSourceBesideSource(Asset $asset, ?string $defaultSourceId): bool
    {
        $sources = $asset->getSources();
        if (null === $defaultSourceId || $sources->count() < 2) {
            return false;
        }

        foreach ($sources->toArray() as $source) {
            if ((string) $source->getId() === $defaultSourceId) {
                $sources->removeElement($source);

                return true;
            }
        }

        return false;
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
