<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Builds author data the way it exists in production: variants, twins that differ only in the identifier,
 * and children with one or more current authors. A spec is `key => [name, reviewed?, identifier?, parent keys?]`.
 */
trait AuthorTreeTrait
{
    /**
     * @param array<string, array{name: string, reviewed?: bool, identifier?: string, parents?: list<string>}> $specs
     *
     * @return array<string, Author>
     */
    private function createAuthorTree(ExtSystem $extSystem, array $specs): array
    {
        $authors = [];
        foreach ($specs as $key => $spec) {
            $parents = array_map(static fn (string $parentKey): Author => $authors[$parentKey], $spec['parents'] ?? []);
            $author = (new Author())
                ->setName($spec['name'])
                ->setIdentifier($spec['identifier'] ?? App::EMPTY_STRING)
                ->setExtSystem($extSystem)
                ->setCurrentAuthors(new ArrayCollection($parents));
            $author->getFlags()->setReviewed($spec['reviewed'] ?? false);
            $authors[$key] = $this->getService(AuthorManager::class)->create($author);
        }

        return $authors;
    }

    /**
     * @param array<string, Author> $authors
     * @param Collection<array-key, Author> $assetAuthors
     *
     * @return list<string> keys of the tree, or `name:<name>` for an author outside it (created by the tested path or a fixture)
     */
    private function toTreeKeys(array $authors, Collection $assetAuthors): array
    {
        $keysById = array_flip(array_map(static fn (Author $author): string => (string) $author->getId(), $authors));
        $keys = array_map(
            static fn (Author $author): string => $keysById[(string) $author->getId()] ?? 'name:' . $author->getName(),
            $assetAuthors->getValues(),
        );
        sort($keys);

        return $keys;
    }
}
