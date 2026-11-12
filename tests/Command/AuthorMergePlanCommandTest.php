<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Command;

use AnzuSystems\CoreDamBundle\Domain\Author\Merge\AuthorMergeCsv;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuthorMergePlanCommandTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const string COMMAND = 'anzu-dam:author:merge-plan';

    private string $file;

    /**
     * @var array<string, Author>
     */
    private array $authors;

    /**
     * @var array<string, array<string, string>> CSV rows by tree key
     */
    private array $rows;

    protected function setUp(): void
    {
        parent::setUp();
        $cms = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->authors = $this->createAuthorTree($cms, [
            'jakubco' => [
                'name' => 'SME - Jozef Jakubčo',
            ],
            'jakubcoDash' => [
                'name' => 'SME- Jozef Jakubčo',
                'reviewed' => true,
            ],
            'jakubcoSpace' => [
                'name' => 'SME -Jozef Jakubčo',
            ],
            'novakPhoto' => [
                'name' => 'Peter Novák',
                'identifier' => 'foto',
            ],
            'novakSport' => [
                'name' => 'Peter Novák',
                'identifier' => 'šport',
            ],
            'novakSportDot' => [
                'name' => 'Peter Novák.',
                'identifier' => 'Šport',
            ],
            'lipaSelf' => [
                'name' => 'Ivan Lipa',
                'identifier' => 'IVAN LIPA',
            ],
            'lipaDot' => [
                'name' => 'Ivan Lipa.',
            ],
            'erd' => [
                'name' => 'Marko Erd',
                'reviewed' => true,
            ],
            'erdChild' => [
                'name' => 'Sme - Marko',
                'parents' => ['erd'],
            ],
            'erdDot' => ['name' => 'Marko Erd.'],
            'erdMapped' => ['name' => 'Marko Erd,', 'parents' => ['erd']],
            'kovacA' => ['name' => 'Ján Kováč'],
            'kovacB' => ['name' => 'Ján Kováč.'],
            'kovacChildA' => ['name' => 'Kováč A', 'parents' => ['kovacA']],
            'kovacChildB' => ['name' => 'Kováč B', 'parents' => ['kovacB']],
            'hraskoCaron' => ['name' => 'Š. Hraško'],
            'hraskoPlain' => ['name' => 'S.Hraško'],
            'alone' => ['name' => 'Zuzana Samostatná'],
            'frank' => ['name' => 'Michal Frank', 'reviewed' => true],
            'korzarMain' => ['name' => 'Korzár/ Michal Frank', 'parents' => ['frank']],
            'korzarVariant' => ['name' => 'Korzár/Michal Frank'],
            'tatran' => ['name' => 'Tatran'],
            'tatranMain' => ['name' => 'Vydavateľstvo Tatran', 'parents' => ['tatran']],
            'tatranMapped' => ['name' => 'Vydavateľstvo Tatran.', 'parents' => ['tatran']],
            'sme' => ['name' => 'SME', 'reviewed' => true],
            'burcik' => ['name' => 'Matúš Burčík', 'reviewed' => true],
            'burcikMain' => ['name' => 'SME- Matúš Burčík', 'parents' => ['sme', 'burcik']],
            'burcikVariant' => ['name' => 'SME – Matúš Burčík'],
            'betaSme' => ['name' => 'Eva Beta', 'parents' => ['sme']],
            'betaBurcik' => ['name' => 'Eva Beta.', 'parents' => ['burcik']],
            'gama' => ['name' => 'Ola Gama'],
            'gamaChild' => ['name' => 'Gamova fotka', 'parents' => ['gama']],
            'gamaSme' => ['name' => 'Ola Gama.', 'parents' => ['sme']],
            'nowakStroke' => ['name' => 'Ł. Nowak'],
            'nowakPlain' => ['name' => 'L. Nowak'],
            'illustrationMain' => ['name' => 'ILUSTRAČNÉ FOTO SME', 'parents' => ['sme']],
            'illustrationVariant' => ['name' => 'ILUSTRAČNÉ FOTO SME.'],
            'krupa' => ['name' => 'Martin Krupa'],
            'krupaAcute' => ['name' => 'Martin Krúpa.'],
            'dots' => ['name' => '.......'],
            'dotsChild' => ['name' => 'Bodky', 'parents' => ['dots']],
        ]);
        $this->linkAssets('jakubco', 2);
        $this->linkAssets('jakubcoDash', 1);
        $this->linkAssets('novakSport', 1);
        $this->linkAssets('lipaSelf', 1);
        $this->linkAssets('tatranMain', 1);
        $this->linkAssets('alone', 10);
        $this->linkAssets('krupa', 1);

        $this->file = sys_get_temp_dir() . '/author-merge-plan-' . bin2hex(random_bytes(8)) . '.csv';
        self::assertSame(Command::SUCCESS, $this->execute());

        $keysById = array_flip(array_map(static fn (Author $author): string => (string) $author->getId(), $this->authors));
        $this->rows = [];
        foreach (AuthorMergeCsv::read($this->file) as $row) {
            if (isset($keysById[$row[AuthorMergeCsv::AUTHOR_ID]])) {
                $this->rows[$keysById[$row[AuthorMergeCsv::AUTHOR_ID]]] = $row;
            }
        }
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        parent::tearDown();
    }

    public function testExistingFileIsNeverOverwritten(): void
    {
        $content = (string) file_get_contents($this->file);

        self::assertSame(Command::FAILURE, $this->execute());
        self::assertSame($content, (string) file_get_contents($this->file));
    }

    public function testFileIsUtf8WithBomForExcel(): void
    {
        self::assertStringStartsWith("\u{FEFF}" . implode(',', AuthorMergeCsv::COLUMNS), (string) file_get_contents($this->file));
    }

    public function testMainIsTheVariantWithMostAssets(): void
    {
        $this->assertGroup(['jakubco' => 'main', 'jakubcoDash' => 'variant', 'jakubcoSpace' => 'variant'], 'A3 (variant reviewed)');
        self::assertSame('2', $this->rows['jakubco'][AuthorMergeCsv::ASSET_COUNT]);
        self::assertSame((string) $this->authors['jakubco']->getId(), $this->rows['jakubcoSpace'][AuthorMergeCsv::TARGET_IDS]);
        self::assertSame('1', $this->rows['jakubco'][AuthorMergeCsv::GROUP_REVIEWED_COUNT]);
    }

    public function testDifferentIdentifiersAreNeverGrouped(): void
    {
        $this->assertGroup(['novakSport' => 'main', 'novakSportDot' => 'variant'], 'A1 (nobody reviewed)');
        self::assertArrayNotHasKey('novakPhoto', $this->rows);
        self::assertArrayNotHasKey('dots', $this->rows);
        self::assertSame('sport', $this->rows['novakSport'][AuthorMergeCsv::GROUP_IDENTIFIER]);
    }

    public function testIdentifierEqualToNameCountsAsNone(): void
    {
        $this->assertGroup(['lipaSelf' => 'main', 'lipaDot' => 'variant'], 'A1 (nobody reviewed)');
        self::assertSame('', $this->rows['lipaSelf'][AuthorMergeCsv::GROUP_IDENTIFIER]);
    }

    public function testCurrentAuthorIsMainAndItsChildrenStay(): void
    {
        $this->assertGroup(['erd' => 'main', 'erdDot' => 'variant', 'erdMapped' => 'no change'], 'A5 (main has children)');
        self::assertSame('Marko Erd', $this->rows['erdMapped'][AuthorMergeCsv::CURRENT_AUTHORS]);
        self::assertSame('2', $this->rows['erd'][AuthorMergeCsv::CHILD_AUTHOR_COUNT]);
    }

    public function testGroupsTheRulesCannotDecideGoToPeopleFirstWithAProposal(): void
    {
        $this->assertGroup(['hraskoCaron' => 'main', 'hraskoPlain' => 'variant'], 'M1 (diacritics)');
        $this->assertGroup(['nowakStroke' => 'main', 'nowakPlain' => 'variant'], 'M1 (diacritics)');
        $this->assertGroup(['betaSme' => 'main', 'betaBurcik' => 'no change'], 'M2 (different current authors)');
        $this->assertGroup(['gama' => 'main', 'gamaSme' => 'no change'], 'M2 (different current authors)');
        $this->assertGroup(['kovacA' => 'do not merge', 'kovacB' => 'do not merge'], 'M3 (two current authors)');
        $this->assertGroup(['krupa' => 'main', 'krupaAcute' => 'variant'], 'M5 (diacritics in name)');
        self::assertSame('SME', $this->rows['betaSme'][AuthorMergeCsv::TARGET_NAMES]);
        self::assertLessThan((int) $this->rows['betaSme'][AuthorMergeCsv::GROUP], (int) $this->rows['hraskoCaron'][AuthorMergeCsv::GROUP]);
        self::assertLessThan((int) $this->rows['kovacA'][AuthorMergeCsv::GROUP], (int) $this->rows['gama'][AuthorMergeCsv::GROUP]);
        self::assertLessThan((int) $this->rows['jakubco'][AuthorMergeCsv::GROUP], (int) $this->rows['kovacA'][AuthorMergeCsv::GROUP]);
    }

    public function testUsedAuthorsWithoutADuplicateFollowAtTheEnd(): void
    {
        self::assertSame('S (single author)', $this->rows['alone'][AuthorMergeCsv::CASE]);
        self::assertSame('no change', $this->rows['alone'][AuthorMergeCsv::DECISION]);
        self::assertSame('S (single author)', $this->rows['frank'][AuthorMergeCsv::CASE]);
        self::assertArrayNotHasKey('novakPhoto', $this->rows);
        self::assertArrayNotHasKey('dots', $this->rows);
        self::assertGreaterThan((int) $this->rows['jakubco'][AuthorMergeCsv::GROUP], (int) $this->rows['frank'][AuthorMergeCsv::GROUP]);
        self::assertLessThan((int) $this->rows['frank'][AuthorMergeCsv::GROUP], (int) $this->rows['alone'][AuthorMergeCsv::GROUP]);
    }

    public function testCurrentAuthorsOfTheMainAuthorGoToTheVariants(): void
    {
        $this->assertGroup(['burcikMain' => 'main', 'burcikVariant' => 'variant'], 'A6 (set by a person)');
        self::assertEqualsCanonicalizing(['SME', 'Matúš Burčík'], AuthorMergeCsv::splitList($this->rows['burcikVariant'][AuthorMergeCsv::TARGET_NAMES]));
        self::assertEqualsCanonicalizing(['SME', 'Matúš Burčík'], AuthorMergeCsv::splitList($this->rows['burcikMain'][AuthorMergeCsv::CURRENT_AUTHORS]));
    }

    public function testCurrentAuthorsThatDropAPartOfTheNameGoToPeople(): void
    {
        $this->assertGroup(['korzarMain' => 'do not merge', 'korzarVariant' => 'do not merge'], 'M4 (name not covered)');
        self::assertStringEndsWith('(korzar)', $this->rows['korzarVariant'][AuthorMergeCsv::REASON]);
        self::assertSame('', $this->rows['korzarVariant'][AuthorMergeCsv::TARGET_IDS]);
        $this->assertGroup(['illustrationMain' => 'main', 'illustrationVariant' => 'variant'], 'A6 (set by a person)');
        $this->assertGroup(['tatranMain' => 'main', 'tatranMapped' => 'no change'], 'A7 (already clean)');
    }

    /**
     * @param array<string, string> $decisions all members of one group, the main author first
     */
    private function assertGroup(array $decisions, string $case): void
    {
        $keys = array_keys($decisions);
        $group = $this->rows[$keys[0]][AuthorMergeCsv::GROUP];
        $members = array_keys(array_filter($this->rows, static fn (array $row): bool => $row[AuthorMergeCsv::GROUP] === $group));
        self::assertSame($keys, $members);
        foreach ($decisions as $key => $decision) {
            self::assertSame($decision, $this->rows[$key][AuthorMergeCsv::DECISION], $key);
            self::assertSame($case, $this->rows[$key][AuthorMergeCsv::CASE], $key);
            self::assertSame(str_starts_with($case, 'M') ? '0' : '1', $this->rows[$key][AuthorMergeCsv::APPROVED], $key);
            self::assertSame('cms', $this->rows[$key][AuthorMergeCsv::EXT_SYSTEM], $key);
        }
    }

    private function execute(): int
    {
        return (new CommandTester((new Application(self::$kernel))->find(self::COMMAND)))->execute(['--ext-system' => 'cms', '--file' => $this->file]);
    }

    private function linkAssets(string $key, int $count): void
    {
        $connection = $this->entityManager->getConnection();
        $assetIds = $connection->fetchFirstColumn('SELECT id FROM asset ORDER BY id LIMIT ' . $count);
        self::assertCount($count, $assetIds);
        foreach ($assetIds as $assetId) {
            $connection->insert('asset_author', ['asset_id' => $assetId, 'author_id' => (string) $this->authors[$key]->getId()]);
        }
    }
}
