<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorType;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Builds author data the way it exists in production: variants, twins that differ only in the identifier,
 * and children with one or more current authors. A spec is `key => [name, reviewed?, source?, identifier?, parent keys?]`.
 */
trait AuthorTreeTrait
{
    /**
     * @param array<string, array{name: string, reviewed?: bool, source?: bool, identifier?: string, parents?: list<string>}> $specs
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
            $author->getFlags()
                ->setReviewed($spec['reviewed'] ?? false)
                ->setSource($spec['source'] ?? false);
            $authors[$key] = $this->getService(AuthorManager::class)->create($author);
        }

        return $authors;
    }

    /**
     * The test configuration makes the fixture author AUTHOR_5 the default source of cms images.
     */
    private function markDefaultSource(): Author
    {
        $default = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_5);
        self::assertInstanceOf(Author::class, $default);
        $default->getFlags()->setSource(true);
        $this->entityManager->flush();

        return $default;
    }

    private function createSource(string $name, int $extSystemId = ExtSystemFixtures::ID_CMS): Author
    {
        $source = (new Author())
            ->setName($name)
            ->setIdentifier($name)
            ->setType(AuthorType::Agency)
            ->setExtSystem($this->entityManager->find(ExtSystem::class, $extSystemId));
        $source->getFlags()->setSource(true);

        return $this->getService(AuthorManager::class)->create($source);
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
