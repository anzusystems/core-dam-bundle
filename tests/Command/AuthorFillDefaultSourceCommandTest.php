<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CoreDamBundle\Entity\JobImageDefaultSource;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuthorFillDefaultSourceCommandTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const string COMMAND = 'anzu-dam:author:fill-default-source';

    public function testDryRunWritesNothingAndRunCreatesJob(): void
    {
        $this->markDefaultSource();

        $display = $this->execute(Command::SUCCESS, ['--dry-run' => true]);

        self::assertStringContainsString('AgencyA', $display);
        self::assertStringContainsString('with_file', $display);
        self::assertStringContainsString('Dry run, nothing written', $display);
        self::assertSame([], $this->findJobs());

        $this->execute(Command::SUCCESS);

        $jobs = $this->findJobs();
        self::assertCount(1, $jobs);
        self::assertSame(ExtSystemFixtures::ID_CMS, $jobs[0]->getExtSystemId());
    }

    public function testRefusesDefaultThatIsNotSource(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('default_source_not_source');

        $this->execute(Command::FAILURE, ['--dry-run' => true]);
    }

    public function testFailsForExtSystemWithoutDefaultSource(): void
    {
        $display = $this->execute(Command::FAILURE, ['--ext-system' => 'blog']);

        self::assertStringContainsString('have no default source', $display);
        self::assertSame([], $this->findJobs());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function execute(int $expectedCode, array $options = []): string
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));
        $code = $commandTester->execute(['--ext-system' => 'cms', ...$options]);
        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertSame($expectedCode, $code, $display);

        return $display;
    }

    /**
     * @return list<JobImageDefaultSource>
     */
    private function findJobs(): array
    {
        return $this->entityManager->getRepository(JobImageDefaultSource::class)->findAll();
    }
}
