<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuthorNormalizeNamesCommandTest extends CoreDamKernelTestCase
{
    private const string COMMAND = 'anzu-dam:author:normalize-names';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->entityManager->getConnection();
        $this->connection->update('author', ['name_normalized' => App::EMPTY_STRING], ['id' => AuthorFixtures::AUTHOR_1]);
    }

    public function testFillsEmptyNormalizedName(): void
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame('larry queen', $this->findNameNormalized(AuthorFixtures::AUTHOR_1));
        self::assertMatchesRegularExpression('/updated 1\b/', $commandTester->getDisplay());
    }

    public function testAllKeepsCurrentNormalizedNames(): void
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--all' => true]));
        self::assertMatchesRegularExpression('/updated 0\b/', $commandTester->getDisplay());
        self::assertSame('larry queen', $this->findNameNormalized(AuthorFixtures::AUTHOR_1));
    }

    private function findNameNormalized(string $authorId): string
    {
        $this->entityManager->clear();
        $author = $this->entityManager->find(Author::class, $authorId);
        self::assertInstanceOf(Author::class, $author);

        return $author->getNameNormalized();
    }
}
