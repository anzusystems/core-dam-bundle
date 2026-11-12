<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class AuthorProviderTest extends CoreDamKernelTestCase
{
    private AuthorProvider $authorProvider;
    private AuthorManager $authorManager;
    private ExtSystem $extSystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorProvider = $this->getService(AuthorProvider::class);
        $this->authorManager = $this->getService(AuthorManager::class);
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $this->extSystem = $extSystem;
    }

    public function testExactMatchWins(): void
    {
        $exact = $this->createAuthor('Resolver Exact');
        $this->createAuthor('Resolver  exact.');

        self::assertSame($exact->getId(), $this->authorProvider->resolve('Resolver Exact', $this->extSystem)?->getId());
    }

    public function testSingleNormalizedMatch(): void
    {
        $author = $this->createAuthor('Resolver Jakubčo');

        self::assertSame($author->getId(), $this->authorProvider->resolve('RESOLVER JAKUBCO.', $this->extSystem)?->getId());
    }

    public function testChildMatchKeepsAllItsCurrentAuthorsOnAsset(): void
    {
        $agency = $this->createAuthor('Resolver Agency', [], reviewed: true);
        $photographer = $this->createAuthor('Resolver Photographer');
        $child = $this->createAuthor('Resolver Agency / Photographer', [$agency, $photographer]);

        $resolved = $this->authorProvider->resolve('resolver agency/photographer.', $this->extSystem);
        self::assertInstanceOf(Author::class, $resolved);
        self::assertSame($child->getId(), $resolved->getId());

        $asset = (new Asset())->addAuthor($resolved);
        self::assertTrue($this->authorProvider->provideCurrentAuthorToColl($asset));
        self::assertSame(
            [$agency->getId(), $photographer->getId()],
            $asset->getAuthors()->map(static fn (Author $author): ?string => $author->getId())->getValues()
        );
    }

    public function testAmbiguousMatchResolvesToNothingAndProvideCreatesNewAuthor(): void
    {
        $this->createAuthor('Resolver Twin A');
        $this->createAuthor('Resolver  Twin   A.');

        self::assertNull($this->authorProvider->resolve('Resolver Twin A,', $this->extSystem));

        $created = $this->authorProvider->provideByTitle('Resolver TWIN A,', $this->extSystem);
        self::assertInstanceOf(Author::class, $created);
        self::assertSame('Resolver TWIN A,', $created->getName());
    }

    public function testAmbiguousMatchWithOneReviewedResolvesToReviewed(): void
    {
        $this->createAuthor('Resolver Twin B');
        $reviewed = $this->createAuthor('Resolver  Twin B.', [], reviewed: true);

        self::assertSame($reviewed->getId(), $this->authorProvider->resolve('Resolver Twin B,', $this->extSystem)?->getId());
    }

    public function testIdentifierRestrictsMatches(): void
    {
        $this->createAuthor('Resolver Identified', [], false, 'first');
        $second = $this->createAuthor('Resolver  Identified.', [], false, 'second');

        self::assertNull($this->authorProvider->resolve('Resolver Identified,', $this->extSystem));
        self::assertSame(
            $second->getId(),
            $this->authorProvider->resolve('Resolver Identified,', $this->extSystem, 'second')?->getId()
        );
    }

    public function testReviewedChildIsReplacedByItsCurrentAuthorOnAsset(): void
    {
        $parent = $this->createAuthor('Resolver Asset Parent');
        $child = $this->createAuthor('Resolver Asset Child', [$parent], reviewed: true);
        $asset = (new Asset())->addAuthor($child);

        self::assertTrue($this->authorProvider->provideCurrentAuthorToColl($asset));
        self::assertSame([$parent->getId()], $asset->getAuthors()->map(static fn (Author $author): ?string => $author->getId())->getValues());
    }

    /**
     * @param list<Author> $currentAuthors
     */
    private function createAuthor(string $name, array $currentAuthors = [], bool $reviewed = false, string $identifier = ''): Author
    {
        $author = (new Author())
            ->setName($name)
            ->setIdentifier($identifier)
            ->setExtSystem($this->extSystem)
            ->setCurrentAuthors(new ArrayCollection($currentAuthors));
        $author->getFlags()->setReviewed($reviewed);

        return $this->authorManager->create($author);
    }
}
