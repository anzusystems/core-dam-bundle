<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorFacade;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorNameReindexRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorFacadeTest extends CoreDamKernelTestCase
{
    private AuthorFacade $authorFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorFacade = $this->getService(AuthorFacade::class);
    }

    #[DataProvider('updateNameChangeDataProvider')]
    public function testUpdateCreatesReindexJobOnlyWhenNameChanges(string $fixtureAuthorId, ?string $newName, bool $expectReindexJob): void
    {
        $author = $this->getFixtureAuthor($fixtureAuthorId);
        $authorId = (string) $author->getId();
        $newAuthor = $this->buildNewAuthorState($author, $newName ?? $author->getName());
        if (null === $newName) {
            $newAuthor->setIdentifier('changed-identifier');
        }

        $this->authorFacade->update($author, $newAuthor);
        $this->entityManager->clear();
        $job = $this->findReindexJobForAuthor($authorId);

        if (false === $expectReindexJob) {
            $this->assertNull($job);

            return;
        }
        $this->assertInstanceOf(JobAuthorNameReindex::class, $job);
        $this->assertSame($authorId, $job->getAuthorId());
    }

    public static function updateNameChangeDataProvider(): array
    {
        return [
            'name_changed_creates_job' => [
                'fixtureAuthorId' => AuthorFixtures::AUTHOR_2,
                'newName' => 'Renamed Author Two',
                'expectReindexJob' => true,
            ],
            'name_unchanged_skips_job' => [
                'fixtureAuthorId' => AuthorFixtures::AUTHOR_3,
                'newName' => null,
                'expectReindexJob' => false,
            ],
        ];
    }

    private function getFixtureAuthor(string $id): Author
    {
        /** @var Author $author */
        $author = $this->getService(AuthorRepository::class)->find($id);

        return $author;
    }

    private function buildNewAuthorState(Author $author, string $name): Author
    {
        return (new Author())
            ->setId($author->getId())
            ->setName($name)
            ->setIdentifier($author->getIdentifier())
            ->setExtSystem($author->getExtSystem())
            ->setFlags($author->getFlags())
            ->setType($author->getType())
        ;
    }

    private function findReindexJobForAuthor(string $authorId): ?JobAuthorNameReindex
    {
        return $this->getService(JobAuthorNameReindexRepository::class)->findOneBy(['authorId' => $authorId]);
    }
}
