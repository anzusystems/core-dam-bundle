<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\Author\Merge\AuthorMergeCsv;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseManager;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobAuthorCurrentOptimizeProcessor;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuthorMergeCommandTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const string COMMAND = 'anzu-dam:author:merge';
    private const array TREE = [
        'main' => [
            'name' => 'Jozef Jakubčo',
        ],
        'reviewed' => [
            'name' => 'Jozef Jakubčo.',
            'reviewed' => true,
        ],
        'spaces' => [
            'name' => 'Jozef  Jakubčo',
        ],
        'skipped' => [
            'name' => 'Jozef Jakubčo,',
        ],
        'other' => [
            'name' => 'Peter Novák',
        ],
        'otherDot' => [
            'name' => 'Peter Novák.',
        ],
    ];

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
        $this->file = (string) tempnam(sys_get_temp_dir(), 'author-merge');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        parent::tearDown();
    }

    public function testMergeMovesApprovalHierarchyCleanPhraseAndAssets(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $asset = $this->findCmsAsset();
        $asset->getAuthors()->clear();
        $asset->addAuthor($this->authors['reviewed'])->addAuthor($this->authors['spaces'])->addAuthor($this->authors['other']);
        $this->entityManager->flush();
        $phrase = $this->getService(AuthorCleanPhraseManager::class)->create(
            (new AuthorCleanPhrase())
                ->setExtSystem($this->cms)
                ->setPhrase('JJ')
                ->setMode(AuthorCleanPhraseMode::Replace)
                ->setType(AuthorCleanPhraseType::Word)
                ->setAuthorReplacement($this->authors['spaces'])
        );
        $this->writeCsv([
            '1' => [
                'main' => 'hlavny',
                'reviewed' => 'podriadeny',
                'spaces' => 'podriadeny',
                'skipped' => 'vynechat',
            ],
            '2' => [
                'other' => 'nezlucovat',
                'otherDot' => '',
            ],
        ]);

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 1 groups \(2 variants, 2 asset link snapshots, 1 clean phrases\), 1 jobs, 0 groups already merged, 0 skipped/', $display);
        $main = $this->findAuthor('main');
        self::assertTrue($main->getFlags()->isReviewed());
        self::assertTrue($main->getFlags()->isCanBeCurrentAuthor());
        foreach (['reviewed', 'spaces'] as $key) {
            $variant = $this->findAuthor($key);
            self::assertSame(['main'], $this->toTreeKeys($this->authors, $variant->getCurrentAuthors()), $key);
            self::assertFalse($variant->getFlags()->isReviewed(), $key);
            self::assertFalse($variant->getFlags()->isCanBeCurrentAuthor(), $key);
        }
        foreach (['skipped', 'other', 'otherDot'] as $key) {
            self::assertTrue($this->findAuthor($key)->getCurrentAuthors()->isEmpty(), $key);
        }
        self::assertSame((string) $main->getId(), (string) $this->entityManager->find(AuthorCleanPhrase::class, $phrase->getId())?->getAuthorReplacement()?->getId());

        $logs = $this->fetchLogs();
        self::assertSame(
            [
                ['Jozef  Jakubčo', 0, 1, 'Jozef Jakubčo', 0, '1'],
                ['Jozef Jakubčo.', 1, 1, 'Jozef Jakubčo', 0, '1'],
            ],
            array_map(static fn (array $log): array => [
                $log['variant_name'],
                (int) $log['variant_reviewed'],
                (int) $log['variant_can_be_current_author'],
                $log['main_author_name'],
                (int) $log['main_author_reviewed'],
                $log['group_name'],
            ], $logs),
        );
        $snapshot = $this->fetchSnapshot();
        self::assertEqualsCanonicalizing(
            [(string) $this->authors['reviewed']->getId(), (string) $this->authors['spaces']->getId()],
            array_column($snapshot, 'author_id'),
        );
        foreach ($snapshot as $row) {
            self::assertSame((string) $asset->getId(), $row['asset_id']);
            self::assertSame((string) $main->getId(), $row['main_author_id']);
            self::assertSame($logs[0]['run_id'], $row['run_id']);
        }

        $this->processJobs('main');
        self::assertSame(['main', 'other'], $this->toTreeKeys($this->authors, $this->findCmsAsset()->getAuthors()));
    }

    public function testVariantsGoUnderTheTargetAuthorsTheCsvApproved(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'sme' => ['name' => 'SME', 'reviewed' => true],
            'lipa' => ['name' => 'Ivan Lipa', 'reviewed' => true],
            'main' => ['name' => 'SME - Ivan Lipa', 'parents' => ['sme', 'lipa']],
            'variant' => ['name' => 'SME- Ivan Lipa', 'reviewed' => true],
        ]);
        $asset = $this->findCmsAsset();
        $asset->addAuthor($this->authors['variant']);
        $this->entityManager->flush();
        $this->writeCsv(['7' => ['main' => 'hlavny', 'variant' => 'podriadeny']]);
        $main = $this->findAuthor('main');
        $main->getCurrentAuthors()->removeElement($this->findAuthor('lipa'));
        $this->entityManager->flush();

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 1 groups \(1 variants, 2 asset link snapshots, 0 clean phrases\), 2 jobs/', $display);
        self::assertSame(['lipa', 'sme'], $this->toTreeKeys($this->authors, $this->findAuthor('variant')->getCurrentAuthors()));
        self::assertCount(2, array_filter($this->fetchLogs(), fn (array $log): bool => (string) $this->authors['variant']->getId() === $log['variant_id']));
        self::assertEqualsCanonicalizing(
            [(string) $this->authors['sme']->getId(), (string) $this->authors['lipa']->getId()],
            array_column($this->fetchSnapshot(), 'main_author_id'),
        );
        self::assertEqualsCanonicalizing(
            [(string) $this->authors['sme']->getId(), (string) $this->authors['lipa']->getId()],
            array_map(static fn (JobAuthorCurrentOptimize $job): ?string => $job->getAuthorId(), $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findAll()),
        );
    }

    public function testGroupsUnderOneAuthorShareItsWaitingJob(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'sme' => ['name' => 'SME', 'reviewed' => true],
            'lipa' => ['name' => 'Ivan Lipa', 'reviewed' => true],
            'lipaMain' => ['name' => 'SME - Ivan Lipa', 'parents' => ['sme', 'lipa']],
            'lipaVariant' => ['name' => 'SME- Ivan Lipa'],
            'malaMain' => ['name' => 'SME - Eva Malá', 'parents' => ['sme']],
            'malaVariant' => ['name' => 'SME- Eva Malá'],
        ]);
        $asset = $this->findCmsAsset();
        $asset->getAuthors()->clear();
        $asset->addAuthor($this->authors['malaVariant']);
        $this->entityManager->flush();
        $this->writeCsv(['1' => ['lipaMain' => 'hlavny', 'lipaVariant' => 'podriadeny'], '2' => ['malaMain' => 'hlavny', 'malaVariant' => 'podriadeny']]);

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 2 groups .* 2 jobs/', $display);
        self::assertEqualsCanonicalizing(
            [(string) $this->authors['sme']->getId(), (string) $this->authors['lipa']->getId()],
            array_map(static fn (JobAuthorCurrentOptimize $job): ?string => $job->getAuthorId(), $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findAll()),
        );
        self::assertSame(['sme'], $this->toTreeKeys($this->authors, $this->findAuthor('malaVariant')->getCurrentAuthors()));

        $this->processJobs('sme');
        self::assertSame(['sme'], $this->toTreeKeys($this->authors, $this->findCmsAsset()->getAuthors()));
    }

    /**
     * @param array<string, array<string, string>> $decisions
     * @param Closure(array<int, array<string, string|int>>): array<int, array<string, string|int>>|null $editRows
     */
    #[DataProvider('invalidCsvDataProvider')]
    public function testInvalidCsvChangesNothing(array $decisions, ?Closure $editRows, string $error): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv($decisions, $editRows);

        self::assertStringContainsString($error, $this->execute(Command::FAILURE));
        self::assertTrue($this->findAuthor('reviewed')->getCurrentAuthors()->isEmpty());
        self::assertSame([], $this->fetchLogs());
    }

    public static function invalidCsvDataProvider(): iterable
    {
        $valid = ['main' => 'hlavny', 'reviewed' => 'podriadeny'];

        yield 'two main authors' => [
            ['1' => ['main' => 'hlavny', 'reviewed' => 'hlavny', 'spaces' => 'podriadeny']], null, 'Group 1: 2 rows "hlavny"',
        ];
        yield 'variant without a main author' => [
            ['1' => ['main' => 'bez zmeny', 'reviewed' => 'podriadeny']], null, 'Group 1: rows "podriadeny" without a row "hlavny"',
        ];
        yield 'unknown decision' => [
            ['1' => ['main' => 'hlavný', 'reviewed' => 'podriadeny']], null, 'unknown decision "hlavný"',
        ];
        yield 'author that does not exist, in a valid group of its own' => [
            ['1' => $valid, '2' => ['other' => 'hlavny', 'otherDot' => 'podriadeny']],
            static fn (array $rows): array => [...$rows, 3 => [...$rows[3], AuthorMergeCsv::AUTHOR_ID => '00000000-0000-0000-0000-000000000000']],
            'author "00000000-0000-0000-0000-000000000000" does not exist',
        ];
        yield 'row of another ext system' => [
            ['1' => $valid], static fn (array $rows): array => [...$rows, 1 => [...$rows[1], AuthorMergeCsv::EXT_SYSTEM => 'blog']], 'ext system "blog" is not "cms"',
        ];
        yield 'author of another ext system' => [
            ['1' => $valid],
            static fn (array $rows): array => [...$rows, 1 => [...$rows[1], AuthorMergeCsv::AUTHOR_ID => AuthorFixtures::AUTHOR_BLOG_1]],
            'belongs to another ext system',
        ];
        yield 'author in two rows' => [
            ['1' => $valid, '2' => ['other' => 'hlavny', 'otherDot' => 'podriadeny']],
            static fn (array $rows): array => [...$rows, 3 => [...$rows[3], AuthorMergeCsv::AUTHOR_ID => $rows[0][AuthorMergeCsv::AUTHOR_ID]]],
            'is already on row 2',
        ];
        yield 'group name longer than the log column' => [
            ['1' => $valid],
            static fn (array $rows): array => array_map(static fn (array $row): array => [...$row, AuthorMergeCsv::GROUP => str_repeat('x', 65)], $rows),
            'the name is longer than 64 characters',
        ];
        yield 'group of two different authors' => [
            ['1' => ['main' => 'hlavny', 'other' => 'podriadeny']], null, 'Group 1: merges different authors',
        ];
        yield 'target author that does not exist' => [
            ['1' => $valid],
            static fn (array $rows): array => array_map(static fn (array $row): array => [...$row, AuthorMergeCsv::TARGET_IDS => '00000000-0000-0000-0000-000000000000'], $rows),
            'Row 2: author "00000000-0000-0000-0000-000000000000" does not exist',
        ];
        yield 'variant as the target author' => [
            ['1' => $valid],
            static fn (array $rows): array => array_map(static fn (array $row): array => [...$row, AuthorMergeCsv::TARGET_IDS => $rows[1][AuthorMergeCsv::AUTHOR_ID]], $rows),
            'Group 1: target author',
        ];
        yield 'variant of another group as the target author' => [
            ['1' => $valid, '2' => ['other' => 'hlavny', 'otherDot' => 'podriadeny']],
            static fn (array $rows): array => [
                ...$rows,
                2 => [...$rows[2], AuthorMergeCsv::TARGET_IDS => $rows[1][AuthorMergeCsv::AUTHOR_ID]],
                3 => [...$rows[3], AuthorMergeCsv::TARGET_IDS => $rows[1][AuthorMergeCsv::AUTHOR_ID]],
            ],
            'is a variant of group 1',
        ];
        yield 'target author changed on a variant row' => [
            ['1' => $valid],
            static fn (array $rows): array => [...$rows, 1 => [...$rows[1], AuthorMergeCsv::TARGET_IDS => (string) $rows[0][AuthorMergeCsv::AUTHOR_ID] . ' + 00000000-0000-0000-0000-000000000000']],
            'Row 3: "nadriadeny_id" of a variant differs from its main author',
        ];
        yield 'missing decision column' => [
            ['1' => $valid], static fn (array $rows): array => array_map(static fn (array $row): array => array_diff_key($row, [AuthorMergeCsv::DECISION => true]), $rows), 'Missing columns: rozhodnutie',
        ];
    }

    public function testEmptyTargetsOnTheMainRowMeanTheMainAuthor(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(
            ['1' => ['main' => 'hlavny', 'reviewed' => 'podriadeny']],
            static fn (array $rows): array => [...$rows, 0 => [...$rows[0], AuthorMergeCsv::TARGET_IDS => '']],
        );

        $this->execute(Command::SUCCESS);

        self::assertSame(['main'], $this->toTreeKeys($this->authors, $this->findAuthor('reviewed')->getCurrentAuthors()));
    }

    public function testFileNotSavedAsUtf8ChangesNothing(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'hlavny', 'reviewed' => 'podriadeny']]);
        file_put_contents($this->file, mb_convert_encoding((string) file_get_contents($this->file), 'ISO-8859-2', 'UTF-8'));

        self::assertStringContainsString('not UTF-8', $this->execute(Command::FAILURE));
        self::assertTrue($this->findAuthor('reviewed')->getCurrentAuthors()->isEmpty());
    }

    /**
     * @param Closure(array<string, Author>, AuthorManager): void $change
     */
    #[DataProvider('changedAfterCsvDataProvider')]
    public function testGroupChangedAfterCsvIsSkippedAndOthersMerge(Closure $change, string $reason): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [...self::TREE, 'root' => ['name' => 'Iná Osoba', 'reviewed' => true]]);
        $this->writeCsv(['1' => ['main' => 'hlavny', 'reviewed' => 'podriadeny', 'spaces' => 'podriadeny'], '2' => ['other' => 'hlavny', 'otherDot' => 'podriadeny']]);
        $change($this->authors, $this->getService(AuthorManager::class));
        $this->entityManager->flush();

        $display = $this->execute(Command::SUCCESS);

        self::assertStringContainsString($reason, $display);
        self::assertMatchesRegularExpression('/merged 1 groups .* 1 skipped/', $display);
        self::assertTrue($this->findAuthor('reviewed')->getCurrentAuthors()->isEmpty());
        self::assertSame([], array_filter($this->fetchLogs(), static fn (array $log): bool => '1' === $log['group_name']));
        self::assertSame(['other'], $this->toTreeKeys($this->authors, $this->findAuthor('otherDot')->getCurrentAuthors()));
        self::assertFalse($this->findAuthor('other')->getFlags()->isReviewed());
    }

    public static function changedAfterCsvDataProvider(): iterable
    {
        yield 'variant renamed' => [
            static function (array $authors): void {
                $authors['reviewed']->setName('Jozef Jakubčo..');
            },
            'was changed or deleted',
        ];
        yield 'variant got an identifier (a namesake)' => [
            static function (array $authors): void {
                $authors['spaces']->setIdentifier('šport');
            },
            'was changed or deleted',
        ];
        yield 'main author got another identifier' => [
            static function (array $authors): void {
                $authors['main']->setIdentifier('foto');
            },
            'main author',
        ];
        yield 'variant got another current author' => [
            static function (array $authors, AuthorManager $manager): void {
                $manager->merge(variants: [$authors['spaces']], targets: [$authors['root']], flush: false);
            },
            'already has other current authors',
        ];
        yield 'main author got a current author' => [
            static function (array $authors, AuthorManager $manager): void {
                $manager->merge(variants: [$authors['main']], targets: [$authors['root']], flush: false);
            },
            'cannot be a current author',
        ];
        yield 'main author cannot be a current author' => [
            static function (array $authors): void {
                $authors['main']->getFlags()->setCanBeCurrentAuthor(false);
            },
            'cannot be a current author',
        ];
        yield 'variant became a current author' => [
            static function (array $authors, AuthorManager $manager): void {
                $manager->merge(variants: [$authors['skipped']], targets: [$authors['spaces']], flush: false);
            },
            'is a current author of other authors',
        ];
    }

    public function testDryRunRollsBackAndCreatesNoJob(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'hlavny', 'reviewed' => 'podriadeny', 'spaces' => 'podriadeny']]);

        $display = $this->execute(Command::SUCCESS, ['--dry-run' => true]);

        self::assertMatchesRegularExpression('/Dry run, rolled back\. Run [0-9a-f-]{36}: merged 1 groups \(2 variants/', $display);
        self::assertTrue($this->findAuthor('reviewed')->getCurrentAuthors()->isEmpty());
        self::assertTrue($this->findAuthor('reviewed')->getFlags()->isReviewed());
        self::assertFalse($this->findAuthor('main')->getFlags()->isReviewed());
        self::assertSame([], $this->fetchLogs());
        self::assertSame([], $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findAll());
    }

    public function testRepeatedRunSkipsMergedGroups(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'hlavny', 'reviewed' => 'podriadeny', 'spaces' => 'podriadeny']]);
        $this->execute(Command::SUCCESS);

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 0 groups .* 0 jobs, 1 groups already merged, 0 skipped/', $display);
        self::assertCount(2, $this->fetchLogs());
        self::assertCount(1, $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findAll());
    }

    /**
     * @param array<string, array<string, string>> $decisions decision by tree key, by group
     * @param Closure(array<int, array<string, string|int>>): array<int, array<string, string|int>>|null $editRows
     */
    private function writeCsv(array $decisions, ?Closure $editRows = null): void
    {
        $rows = [];
        foreach ($decisions as $group => $groupDecisions) {
            $main = $this->authors[(string) array_search('hlavny', $groupDecisions, true)] ?? null;
            $targets = null === $main ? [] : ($main->getCurrentAuthors()->getValues() ?: [$main]);
            foreach ($groupDecisions as $key => $decision) {
                $author = $this->authors[$key];
                $rows[] = [
                    ...array_fill_keys(AuthorMergeCsv::COLUMNS, ''),
                    AuthorMergeCsv::GROUP => (string) $group,
                    AuthorMergeCsv::EXT_SYSTEM => 'cms',
                    AuthorMergeCsv::NAME_NORMALIZED => $author->getNameNormalized(),
                    AuthorMergeCsv::AUTHOR_ID => (string) $author->getId(),
                    AuthorMergeCsv::NAME => $author->getName(),
                    AuthorMergeCsv::IDENTIFIER => $author->getIdentifier(),
                    AuthorMergeCsv::TARGET_IDS => AuthorMergeCsv::joinList(array_map(static fn (Author $target): string => (string) $target->getId(), $targets)),
                    AuthorMergeCsv::DECISION => $decision,
                ];
            }
        }
        if ($editRows) {
            $rows = $editRows($rows);
        }

        $handle = fopen($this->file, 'wb');
        self::assertNotFalse($handle);
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, array_keys($rows[0]), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ';', '"', '');
        }
        fclose($handle);
    }

    /**
     * @return list<array<string, string|int>> by variant name
     */
    private function fetchLogs(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM _author_merge_log ORDER BY variant_name, main_author_name');
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function fetchSnapshot(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM _author_merge_asset_author');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function execute(int $expectedCode, array $options = []): string
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));
        $code = $commandTester->execute(['--ext-system' => 'cms', '--file' => $this->file, ...$options]);
        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertSame($expectedCode, $code, $display);

        return $display;
    }

    private function findAuthor(string $key): Author
    {
        $author = $this->entityManager->find(Author::class, $this->authors[$key]->getId());
        self::assertInstanceOf(Author::class, $author);

        return $author;
    }

    private function findCmsAsset(): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image->getAsset();
    }

    private function processJobs(string $key): void
    {
        $processor = $this->getService(JobAuthorCurrentOptimizeProcessor::class);
        $job = $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findOneBy(['authorId' => (string) $this->authors[$key]->getId()]);
        self::assertInstanceOf(JobAuthorCurrentOptimize::class, $job);
        for ($batch = 0; $batch < 3 && JobStatus::Done !== $job->getStatus(); ++$batch) {
            $processor->process($job);
            $job = $this->entityManager->find(JobAuthorCurrentOptimize::class, $job->getId());
            self::assertInstanceOf(JobAuthorCurrentOptimize::class, $job);
        }
        self::assertSame(JobStatus::Done, $job->getStatus());
        $this->entityManager->clear();
    }
}
