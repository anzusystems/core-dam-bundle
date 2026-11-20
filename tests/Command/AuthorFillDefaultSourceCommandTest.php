<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
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

    public function testDryRunWritesNothingAndRunFills(): void
    {
        $this->markDefaultSource();
        $withFile = $this->countWithFileWithoutSource();
        self::assertGreaterThan(0, $withFile);

        $display = $this->execute(Command::SUCCESS, ['--dry-run' => true]);

        self::assertStringContainsString('AgencyA', $display);
        self::assertStringContainsString('with_file', $display);
        self::assertStringContainsString('Dry run, nothing written', $display);
        self::assertSame($withFile, $this->countWithFileWithoutSource());

        $display = $this->execute(Command::SUCCESS);

        self::assertStringContainsString(sprintf('Filled %d images', $withFile), $display);
        self::assertSame(0, $this->countWithFileWithoutSource());
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

    private function countWithFileWithoutSource(): int
    {
        return $this->getService(AssetSourceDBALRepository::class)
            ->countImagesWithoutSource(ExtSystemFixtures::ID_CMS)[AssetStatus::WithFile->value] ?? 0;
    }
}
