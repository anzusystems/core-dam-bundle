<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobAuthorCurrentOptimizeProcessor;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuthorMarkSourceCommandTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const string COMMAND = 'anzu-dam:author:mark-source';

    private string $file;
    private ExtSystem $cms;

    /**
     * @var array<string, Author>
     */
    private array $authors;

    protected function setUp(): void
    {
        parent::setUp();
        $cms = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->cms = $cms;
        $this->file = (string) tempnam(sys_get_temp_dir(), 'author-mark-source');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        parent::tearDown();
    }

    public function testMarksSourceAndJobMovesLinks(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'source' => [
                'name' => 'Mark Source Agency',
            ],
            'photographer' => [
                'name' => 'Mark Source Photographer',
            ],
            'child' => [
                'name' => 'Mark Source Agency/Photographer',
                'parents' => ['source', 'photographer'],
            ],
            'twin' => [
                'name' => 'Mark Source Twin',
                'identifier' => 'first',
            ],
            'otherTwin' => [
                'name' => 'Mark Source Twin',
                'identifier' => 'second',
            ],
        ]);
        $this->setAssetAuthors($this->findImageAsset(), ['source', 'child']);
        $this->setAssetAuthors($this->findVideoAsset(), ['source']);

        $this->writeNames(['Mark Source Agency', 'Mark Source Agency/Photographer']);
        $display = $this->execute(Command::FAILURE);
        self::assertStringContainsString('Mark Source Agency/Photographer: 0 roots, expected 1', $display);
        $this->writeNames(['Mark Source Agency', 'mark source agency']);
        $display = $this->execute(Command::FAILURE);
        self::assertStringContainsString('mark source agency: 0 roots, expected 1', $display);
        $this->writeNames(['Mark Source Agency', 'Mark Source Twin']);
        $display = $this->execute(Command::FAILURE);
        self::assertStringContainsString('Mark Source Twin: 2 roots, expected 1', $display);
        self::assertFalse($this->findAuthor('source')->getFlags()->isSource());
        self::assertNull($this->findJob());

        $this->writeNames(['Mark Source Agency']);
        $display = $this->execute(Command::SUCCESS, ['--dry-run' => true]);
        self::assertStringContainsString('would create', $display);
        self::assertStringContainsString('Dry run, nothing written', $display);
        self::assertFalse($this->findAuthor('source')->getFlags()->isSource());
        self::assertNull($this->findJob());

        $display = $this->execute(Command::SUCCESS);
        self::assertStringContainsString('Marked 1 sources, 1 jobs', $display);
        self::assertMatchesRegularExpression('/Mark Source Agency \S+ 2 created/', $display);
        self::assertTrue($this->findAuthor('source')->getFlags()->isSource());

        $display = $this->execute(Command::SUCCESS);
        self::assertStringContainsString('Marked 1 sources, 0 jobs', $display);
        self::assertMatchesRegularExpression('/Mark Source Agency \S+ 2 waiting/', $display);
        $this->processJob();

        $image = $this->findImageAsset();
        self::assertSame(['photographer'], $this->toTreeKeys($this->authors, $image->getAuthors()));
        self::assertSame(['source'], $this->toTreeKeys($this->authors, $image->getSources()));
        self::assertSame(['source'], $this->toTreeKeys($this->authors, $this->findVideoAsset()->getAuthors()));
        self::assertSame([], $this->toTreeKeys($this->authors, $this->findVideoAsset()->getSources()));

        $display = $this->execute(Command::SUCCESS);
        self::assertStringContainsString('Marked 1 sources, 1 jobs', $display);
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidInputDataProvider')]
    public function testRejectsInvalidInput(
        array $options,
        ?string $fileContent,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        if (null !== $fileContent) {
            file_put_contents($this->file, $fileContent);
            $options['--file'] = $this->file;
        }

        $display = $this->executeInput($options, $expectedCode);

        self::assertStringContainsString($expectedMessage, $display);
    }

    public static function invalidInputDataProvider(): array
    {
        return [
            'Missing ext system' => [
                'options' => [],
                'fileContent' => "SME\n",
                'expectedCode' => Command::INVALID,
                'expectedMessage' => 'are required',
            ],
            'Unknown ext system' => [
                'options' => ['--ext-system' => 'unknown'],
                'fileContent' => "SME\n",
                'expectedCode' => Command::INVALID,
                'expectedMessage' => 'are required',
            ],
            'Missing file' => [
                'options' => ['--ext-system' => 'cms'],
                'fileContent' => null,
                'expectedCode' => Command::INVALID,
                'expectedMessage' => 'are required',
            ],
            'Unreadable file' => [
                'options' => ['--ext-system' => 'cms', '--file' => '/nonexistent/sources.txt'],
                'fileContent' => null,
                'expectedCode' => Command::FAILURE,
                'expectedMessage' => 'cannot be read',
            ],
            'File without names' => [
                'options' => ['--ext-system' => 'cms'],
                'fileContent' => "\n  \n",
                'expectedCode' => Command::FAILURE,
                'expectedMessage' => 'has no name',
            ],
        ];
    }

    /**
     * @param list<string> $keys
     */
    private function setAssetAuthors(Asset $asset, array $keys): void
    {
        $asset->getAuthors()->clear();
        foreach ($keys as $key) {
            $asset->addAuthor($this->authors[$key]);
        }
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $names
     */
    private function writeNames(array $names): void
    {
        file_put_contents($this->file, implode("\n", $names) . "\n");
    }

    /**
     * @param array<string, mixed> $options
     */
    private function execute(int $expectedCode, array $options = []): string
    {
        return $this->executeInput(['--ext-system' => 'cms', '--file' => $this->file, ...$options], $expectedCode);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function executeInput(array $input, int $expectedCode): string
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));
        $code = $commandTester->execute($input);
        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertSame($expectedCode, $code, $display);

        return $display;
    }

    private function findAuthor(string $key): Author
    {
        $this->entityManager->clear();
        $author = $this->entityManager->find(Author::class, $this->authors[$key]->getId());
        self::assertInstanceOf(Author::class, $author);

        return $author;
    }

    private function findJob(): ?JobAuthorCurrentOptimize
    {
        return $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findOneBy(['authorId' => (string) $this->authors['source']->getId()]);
    }

    private function findImageAsset(): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image->getAsset();
    }

    private function findVideoAsset(): Asset
    {
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $video);

        return $video->getAsset();
    }

    private function processJob(): void
    {
        $job = $this->findJob();
        self::assertInstanceOf(JobAuthorCurrentOptimize::class, $job);
        $this->getService(JobAuthorCurrentOptimizeProcessor::class)->process($job);
        $job = $this->entityManager->find(JobAuthorCurrentOptimize::class, $job->getId());
        self::assertInstanceOf(JobAuthorCurrentOptimize::class, $job);
        self::assertSame(JobStatus::Done, $job->getStatus());
        $this->entityManager->clear();
    }
}
