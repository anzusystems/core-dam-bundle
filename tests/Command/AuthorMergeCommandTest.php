<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\App;
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
use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;
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
                'main' => 'main',
                'reviewed' => 'variant',
                'spaces' => 'variant',
                'skipped' => 'skip',
            ],
            '2' => [
                'other' => 'do not merge',
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
        $this->writeCsv(['7' => ['main' => 'main', 'variant' => 'variant']]);
        $main = $this->findAuthor('main');
        $main->getCurrentAuthors()->removeElement($this->findAuthor('lipa'));
        $this->entityManager->flush();

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 1 groups \(1 variants, 2 asset link snapshots, 0 clean phrases\), 2 jobs/', $display);
        self::assertSame(['lipa', 'sme'], $this->toTreeKeys($this->authors, $this->findAuthor('variant')->getCurrentAuthors()));
        self::assertSame(['sme'], $this->toTreeKeys($this->authors, $this->findAuthor('main')->getCurrentAuthors()));
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

    public function testMainThatIsNotItsTargetGoesUnderTheTargetsAlsoAlone(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'tasr' => ['name' => 'TASR', 'reviewed' => true],
            'tasrDot' => ['name' => 'TASR.'],
            'paren' => ['name' => '(TASR)', 'reviewed' => true],
        ]);
        $this->writeCsv(
            ['1' => ['tasr' => 'main', 'tasrDot' => 'variant'], '2' => ['tasr' => 'no change', 'paren' => 'main']],
            fn (array $rows): array => [...$rows, 3 => [...$rows[3], AuthorMergeCsv::TARGET_IDS => (string) $this->authors['tasr']->getId()]],
        );

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 2 groups \(2 variants/', $display);
        self::assertSame(['tasr'], $this->toTreeKeys($this->authors, $this->findAuthor('paren')->getCurrentAuthors()));
        self::assertFalse($this->findAuthor('paren')->getFlags()->isReviewed());
        self::assertTrue($this->findAuthor('tasr')->getCurrentAuthors()->isEmpty());
    }

    public function testMainWithChildAuthorsTakesThemUnderItsTargets(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'tasr' => ['name' => 'TASR', 'reviewed' => true],
            'paren' => ['name' => '(TASR)', 'reviewed' => true],
            'parenChild' => ['name' => '(TASR) ilustračné', 'parents' => ['paren']],
        ]);
        $this->writeCsv(
            ['1' => ['paren' => 'main']],
            fn (array $rows): array => [0 => [...$rows[0], AuthorMergeCsv::TARGET_IDS => (string) $this->authors['tasr']->getId()]],
        );

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 1 groups \(1 variants, .* 0 skipped; 1 child authors moved along, 0 target authors created/', $display);
        self::assertSame(['tasr'], $this->toTreeKeys($this->authors, $this->findAuthor('paren')->getCurrentAuthors()));
        self::assertSame(['tasr'], $this->toTreeKeys($this->authors, $this->findAuthor('parenChild')->getCurrentAuthors()));
        self::assertSame(
            [['(TASR)', App::EMPTY_STRING], ['(TASR) ilustračné', (string) $this->authors['paren']->getId()]],
            array_map(static fn (array $log): array => [$log['variant_name'], $log['variant_current_author_ids']], $this->fetchLogs()),
        );
    }

    public function testMainAloneThatIsItsOwnTargetStaysAndItsAssetsGetTheOtherTargets(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'web' => ['name' => 'mesto-sever.sk', 'reviewed' => true],
            'repro' => ['name' => 'Repro Sever', 'source' => true],
        ]);
        $asset = $this->findCmsAsset();
        $asset->getAuthors()->clear();
        $asset->getSources()->clear();
        $asset->addAuthor($this->authors['web']);
        $this->entityManager->flush();
        $this->writeCsv(
            ['1' => ['web' => 'main']],
            fn (array $rows): array => [[...$rows[0], AuthorMergeCsv::TARGET_IDS => AuthorMergeCsv::joinList([(string) $this->authors['web']->getId(), (string) $this->authors['repro']->getId()])]],
        );
        $merged = '/merged 1 groups \(0 variants, 1 asset link snapshots, 0 clean phrases\), %d jobs, 0 groups already merged, 0 skipped/';

        self::assertMatchesRegularExpression(sprintf($merged, 0), $this->execute(Command::SUCCESS, ['--dry-run' => true]));
        self::assertSame([], $this->toTreeKeys($this->authors, $this->findCmsAsset()->getSources()));

        self::assertMatchesRegularExpression(sprintf($merged, 1), $this->execute(Command::SUCCESS));
        $web = $this->findAuthor('web');
        self::assertTrue($web->getCurrentAuthors()->isEmpty());
        self::assertTrue($web->getFlags()->isReviewed());
        self::assertTrue($web->getFlags()->isCanBeCurrentAuthor());
        $asset = $this->findCmsAsset();
        self::assertSame(['web'], $this->toTreeKeys($this->authors, $asset->getAuthors()));
        self::assertSame(['repro'], $this->toTreeKeys($this->authors, $asset->getSources()));
        self::assertSame(
            [[(string) $this->authors['web']->getId(), 0, (string) $this->authors['repro']->getId(), 0]],
            array_map(static fn (array $link): array => [$link['author_id'], (int) $link['source'], $link['main_author_id'], (int) $link['main_author_linked']], $this->fetchSnapshot()),
        );
        self::assertInstanceOf(JobAuthorNameReindex::class, $this->entityManager->getRepository(JobAuthorNameReindex::class)->findOneBy(['authorId' => (string) $this->authors['web']->getId()]));
        self::assertSame([], $this->entityManager->getRepository(JobAuthorCurrentOptimize::class)->findAll());

        self::assertMatchesRegularExpression('/merged 0 groups .* 1 groups already merged, 0 skipped/', $this->execute(Command::SUCCESS));
    }

    public function testMainAloneMergedElsewhereSkipsTheGroup(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'tasr' => ['name' => 'TASR', 'reviewed' => true],
            'other' => ['name' => 'Agentúra Sever', 'reviewed' => true],
            'paren' => ['name' => '(TASR)', 'parents' => ['other']],
        ]);
        $this->writeCsv(
            ['1' => ['paren' => 'main']],
            fn (array $rows): array => [0 => [...$rows[0], AuthorMergeCsv::TARGET_IDS => (string) $this->authors['tasr']->getId()]],
        );

        $display = $this->execute(Command::SUCCESS);

        self::assertStringContainsString(sprintf('author %s already has other current authors', (string) $this->authors['paren']->getId()), $display);
        self::assertSame(['other'], $this->toTreeKeys($this->authors, $this->findAuthor('paren')->getCurrentAuthors()));
    }

    public function testMapRowsGoUnderNamedTargetsFoundOrCreated(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, [
            'agency' => ['name' => 'Agentúra Sever', 'source' => true, 'reviewed' => true],
            'composite' => ['name' => 'Agentúra Sever/Ema Horná'],
            'compositeChild' => ['name' => 'Agentúra Sever / Ema Horná', 'parents' => ['composite']],
            'blank' => ['name' => '???'],
            'twin' => ['name' => 'Ema Horná.'],
        ]);
        $asset = $this->findCmsAsset();
        $asset->getAuthors()->clear();
        $asset->getSources()->clear();
        $asset->addAuthor($this->authors['composite'])->addSource($this->authors['blank'])->addSource($this->authors['agency']);
        $this->entityManager->flush();
        $this->writeCsv(
            ['1' => ['composite' => 'main'], '2' => ['blank' => 'main'], '3' => ['twin' => 'main']],
            static fn (array $rows): array => [
                self::nameTargets($rows[0], sources: 'Agentúra Sever', authors: 'Ema Horná'),
                self::nameTargets($rows[1], sources: 'Neznámy', authors: ''),
                self::nameTargets($rows[2], sources: '', authors: 'Ema Horná'),
            ],
        );

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 3 groups \(3 variants, 3 asset link snapshots, .* 0 skipped; 1 child authors moved along, 2 target authors created/', $display);
        $created = $this->findNamed('Ema Horná');
        self::assertFalse($created->getFlags()->isSource());
        self::assertTrue($this->findNamed('Neznámy')->getFlags()->isSource());
        foreach (['composite', 'compositeChild'] as $key) {
            self::assertSame(['agency', 'name:Ema Horná'], $this->toTreeKeys($this->authors, $this->findAuthor($key)->getCurrentAuthors()), $key);
        }
        self::assertSame(['name:Neznámy'], $this->toTreeKeys($this->authors, $this->findAuthor('blank')->getCurrentAuthors()));
        self::assertSame(['name:Ema Horná'], $this->toTreeKeys($this->authors, $this->findAuthor('twin')->getCurrentAuthors()));
        self::assertSame(
            [
                ['???', 'Neznámy', 1],
                ['Agentúra Sever / Ema Horná', 'Agentúra Sever', 0],
                ['Agentúra Sever / Ema Horná', 'Ema Horná', 1],
                ['Agentúra Sever/Ema Horná', 'Agentúra Sever', 0],
                ['Agentúra Sever/Ema Horná', 'Ema Horná', 1],
                ['Ema Horná.', 'Ema Horná', 0],
            ],
            array_map(static fn (array $log): array => [$log['variant_name'], $log['main_author_name'], (int) $log['main_author_created']], $this->fetchLogs()),
        );
        self::assertEqualsCanonicalizing(
            [
                [(string) $this->authors['composite']->getId(), 0, (string) $this->authors['agency']->getId(), 1],
                [(string) $this->authors['composite']->getId(), 0, (string) $created->getId(), 0],
                [(string) $this->authors['blank']->getId(), 1, (string) $this->findNamed('Neznámy')->getId(), 0],
            ],
            array_map(static fn (array $link): array => [$link['author_id'], (int) $link['source'], $link['main_author_id'], (int) $link['main_author_linked']], $this->fetchSnapshot()),
        );
    }

    public function testDryRunCreatesNoNamedTarget(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, ['composite' => ['name' => 'Agentúra Sever/Ema Horná']]);
        $this->writeCsv(['1' => ['composite' => 'main']], static fn (array $rows): array => [self::nameTargets($rows[0], sources: 'Agentúra Sever', authors: 'Ema Horná')]);

        $display = $this->execute(Command::SUCCESS, ['--dry-run' => true]);

        self::assertMatchesRegularExpression('/Dry run, rolled back\. .* 2 target authors created/', $display);
        self::assertSame([], $this->entityManager->getRepository(Author::class)->findBy(['name' => ['Agentúra Sever', 'Ema Horná']]));
        self::assertTrue($this->findAuthor('composite')->getCurrentAuthors()->isEmpty());
        self::assertSame([], $this->fetchLogs());
    }

    /**
     * @param array<string, array{name: string, source?: bool}> $tree
     */
    #[DataProvider('namedTargetSkippedDataProvider')]
    public function testNamedTargetThatIsNotCertainSkipsTheGroup(array $tree, string $sources, string $authors, string $reason): void
    {
        $this->authors = $this->createAuthorTree($this->cms, ['composite' => ['name' => 'Agentúra Sever/Ema Horná'], ...$tree]);
        $this->writeCsv(['1' => ['composite' => 'main']], static fn (array $rows): array => [self::nameTargets($rows[0], sources: $sources, authors: $authors)]);

        $display = $this->execute(Command::SUCCESS);

        self::assertStringContainsString($reason, $display);
        self::assertTrue($this->findAuthor('composite')->getCurrentAuthors()->isEmpty());
        self::assertSame([], $this->fetchLogs());
    }

    public static function namedTargetSkippedDataProvider(): iterable
    {
        yield 'name spelled otherwise' => [
            ['other' => ['name' => 'Ema Horna']], App::EMPTY_STRING, 'Ema Horná', 'target "Ema Horná" exists as "Ema Horna"',
        ];
        yield 'source that is an author' => [
            ['other' => ['name' => 'Agentúra Sever']], 'Agentúra Sever', 'Ema Horná', 'target "Agentúra Sever" is not a source',
        ];
        yield 'author that is a source' => [
            ['other' => ['name' => 'Ema Horná', 'source' => true]], App::EMPTY_STRING, 'Ema Horná', 'target "Ema Horná" is a source',
        ];
    }

    public function testFileWithoutNamedTargetColumnsMerges(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(
            ['1' => ['main' => 'main', 'reviewed' => 'variant']],
            static fn (array $rows): array => array_map(static fn (array $row): array => array_diff_key($row, [AuthorMergeCsv::NEW_SOURCES => true, AuthorMergeCsv::NEW_AUTHORS => true]), $rows),
        );

        $display = $this->execute(Command::SUCCESS);

        self::assertMatchesRegularExpression('/merged 1 groups \(1 variants/', $display);
        self::assertSame(['main'], $this->toTreeKeys($this->authors, $this->findAuthor('reviewed')->getCurrentAuthors()));
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
        $this->writeCsv(['1' => ['lipaMain' => 'main', 'lipaVariant' => 'variant'], '2' => ['malaMain' => 'main', 'malaVariant' => 'variant']]);

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
        $valid = ['main' => 'main', 'reviewed' => 'variant'];

        yield 'two main authors' => [
            ['1' => ['main' => 'main', 'reviewed' => 'main', 'spaces' => 'variant']], null, 'Group 1: 2 rows "main"',
        ];
        yield 'variant without a main author' => [
            ['1' => ['main' => 'no change', 'reviewed' => 'variant']], null, 'Group 1: rows "variant" without a row "main"',
        ];
        yield 'unknown decision' => [
            ['1' => ['main' => 'Main', 'reviewed' => 'variant']], null, 'unknown decision "Main"',
        ];
        yield 'author that does not exist, in a valid group of its own' => [
            ['1' => $valid, '2' => ['other' => 'main', 'otherDot' => 'variant']],
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
            ['1' => $valid, '2' => ['other' => 'main', 'otherDot' => 'variant']],
            static fn (array $rows): array => [...$rows, 3 => [...$rows[3], AuthorMergeCsv::AUTHOR_ID => $rows[0][AuthorMergeCsv::AUTHOR_ID]]],
            'is already merged on row 2',
        ];
        yield 'group name longer than the log column' => [
            ['1' => $valid],
            static fn (array $rows): array => array_map(static fn (array $row): array => [...$row, AuthorMergeCsv::GROUP => str_repeat('x', 65)], $rows),
            'the name is longer than 64 characters',
        ];
        yield 'group of two different authors' => [
            ['1' => ['main' => 'main', 'other' => 'variant']], null, 'Group 1: merges different authors',
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
            ['1' => $valid, '2' => ['other' => 'main', 'otherDot' => 'variant']],
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
            'Row 3: "target_ids" differs from the main row 2; copy it from row 2 or leave it empty',
        ];
        yield 'target author changed on the main row only' => [
            ['1' => $valid],
            static fn (array $rows): array => [...$rows, 0 => [...$rows[0], AuthorMergeCsv::TARGET_IDS => (string) $rows[0][AuthorMergeCsv::AUTHOR_ID] . ' + ' . AuthorFixtures::AUTHOR_1]],
            'Row 3: "target_ids" differs from the main row 2; copy it from row 2 or leave it empty',
        ];
        yield 'name in new sources and new authors' => [
            ['1' => $valid],
            static fn (array $rows): array => [self::nameTargets($rows[0], sources: 'Ema Horná', authors: 'Ema Horná'), $rows[1]],
            'Row 2: "Ema Horná" is in both "new_sources" and "new_authors"',
        ];
        yield 'new target name changed on a variant row' => [
            ['1' => $valid],
            static fn (array $rows): array => [self::nameTargets($rows[0], sources: '', authors: 'Ema Horná'), [...$rows[1], AuthorMergeCsv::NEW_AUTHORS => 'Eva Horná']],
            'Row 3: "new_authors" differs from the main row 2; copy it from row 2 or leave it empty',
        ];
        yield 'new target written otherwise by another group' => [
            ['1' => $valid, '2' => ['other' => 'main', 'otherDot' => 'variant']],
            static fn (array $rows): array => [
                self::nameTargets($rows[0], sources: '', authors: 'Ema Horná'), self::nameTargets($rows[1], sources: '', authors: ''),
                self::nameTargets($rows[2], sources: '', authors: 'EMA HORNÁ'), self::nameTargets($rows[3], sources: '', authors: ''),
            ],
            'Row 4: "EMA HORNÁ" in "new_authors" is "Ema Horná" in "new_authors" on row 2; write it the same',
        ];
        yield 'new target as a source and as an author' => [
            ['1' => $valid, '2' => ['other' => 'main', 'otherDot' => 'variant']],
            static fn (array $rows): array => [
                self::nameTargets($rows[0], sources: 'Ema Horná', authors: ''), self::nameTargets($rows[1], sources: '', authors: ''),
                self::nameTargets($rows[2], sources: '', authors: 'Ema Horná'), self::nameTargets($rows[3], sources: '', authors: ''),
            ],
            'Row 4: "Ema Horná" in "new_authors" is "Ema Horná" in "new_sources" on row 2; write it the same',
        ];
        yield 'approved that is not 0 or 1' => [
            ['1' => $valid], static fn (array $rows): array => array_map(static fn (array $row): array => [...$row, AuthorMergeCsv::APPROVED => 'yes'], $rows), 'Row 2: "approved" is "yes", allowed: 1, 0',
        ];
        yield 'approved only on a part of the group' => [
            ['1' => $valid], static fn (array $rows): array => [...$rows, 1 => [...$rows[1], AuthorMergeCsv::APPROVED => '0']], 'Group 1: "approved" differs between rows 2, 3',
        ];
        yield 'missing decision column' => [
            ['1' => $valid], static fn (array $rows): array => array_map(static fn (array $row): array => array_diff_key($row, [AuthorMergeCsv::DECISION => true]), $rows), 'Missing columns: decision',
        ];
    }

    public function testGroupNotApprovedChangesNothing(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(
            ['1' => ['main' => 'main', 'reviewed' => 'variant'], '2' => ['other' => 'main', 'otherDot' => 'variant']],
            static fn (array $rows): array => [...$rows, 0 => [...$rows[0], AuthorMergeCsv::APPROVED => '0'], 1 => [...$rows[1], AuthorMergeCsv::APPROVED => '0']],
        );

        $this->execute(Command::SUCCESS);

        self::assertTrue($this->findAuthor('reviewed')->getCurrentAuthors()->isEmpty());
        self::assertSame(['other'], $this->toTreeKeys($this->authors, $this->findAuthor('otherDot')->getCurrentAuthors()));
    }

    public function testEmptyTargetsOnTheMainRowMeanTheMainAuthor(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(
            ['1' => ['main' => 'main', 'reviewed' => 'variant']],
            static fn (array $rows): array => [...$rows, 0 => [...$rows[0], AuthorMergeCsv::TARGET_IDS => '']],
        );

        $this->execute(Command::SUCCESS);

        self::assertSame(['main'], $this->toTreeKeys($this->authors, $this->findAuthor('reviewed')->getCurrentAuthors()));
    }

    public function testFileSavedWithSemicolonsMerges(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'main', 'reviewed' => 'variant', 'skipped' => 'skip']], delimiter: ';');

        $this->execute(Command::SUCCESS);

        self::assertSame(['main'], $this->toTreeKeys($this->authors, $this->findAuthor('reviewed')->getCurrentAuthors()));
    }

    public function testFileNotSavedAsUtf8ChangesNothing(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'main', 'reviewed' => 'variant']]);
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
        $this->writeCsv(['1' => ['main' => 'main', 'reviewed' => 'variant', 'spaces' => 'variant'], '2' => ['other' => 'main', 'otherDot' => 'variant']]);
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
    }

    public function testDryRunRollsBackAndCreatesNoJob(): void
    {
        $this->authors = $this->createAuthorTree($this->cms, self::TREE);
        $this->writeCsv(['1' => ['main' => 'main', 'reviewed' => 'variant', 'spaces' => 'variant']]);

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
        $this->writeCsv(['1' => ['main' => 'main', 'reviewed' => 'variant', 'spaces' => 'variant']]);
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
    private function writeCsv(array $decisions, ?Closure $editRows = null, string $delimiter = ','): void
    {
        $rows = [];
        foreach ($decisions as $group => $groupDecisions) {
            $main = $this->authors[(string) array_search('main', $groupDecisions, true)] ?? null;
            $targets = null === $main ? [] : ($main->getCurrentAuthors()->getValues() ?: [$main]);
            foreach ($groupDecisions as $key => $decision) {
                $author = $this->authors[$key];
                $rows[] = [
                    ...array_fill_keys(AuthorMergeCsv::COLUMNS, ''),
                    AuthorMergeCsv::GROUP => (string) $group,
                    AuthorMergeCsv::APPROVED => '1',
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
        fputcsv($handle, array_keys($rows[0]), $delimiter, '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), $delimiter, '"', '');
        }
        fclose($handle);
    }

    /**
     * @param array<string, string|int> $row
     *
     * @return array<string, string|int>
     */
    private static function nameTargets(array $row, string $sources, string $authors): array
    {
        return [...$row, AuthorMergeCsv::TARGET_IDS => App::EMPTY_STRING, AuthorMergeCsv::NEW_SOURCES => $sources, AuthorMergeCsv::NEW_AUTHORS => $authors];
    }

    private function findNamed(string $name): Author
    {
        $author = $this->entityManager->getRepository(Author::class)->findOneBy(['name' => $name, 'identifier' => App::EMPTY_STRING, 'extSystem' => $this->cms]);
        self::assertInstanceOf(Author::class, $author);

        return $author;
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
