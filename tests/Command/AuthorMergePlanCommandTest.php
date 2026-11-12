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
            'jakubco' => ['name' => 'SME - Jozef Jakubčo'],
            'jakubcoDash' => ['name' => 'SME- Jozef Jakubčo', 'reviewed' => true],
            'jakubcoSpace' => ['name' => 'SME -Jozef Jakubčo'],
            'novakPhoto' => ['name' => 'Peter Novák', 'identifier' => 'foto'],
            'novakSport' => ['name' => 'Peter Novák', 'identifier' => 'šport'],
            'novakSportDot' => ['name' => 'Peter Novák.', 'identifier' => 'Šport'],
            'lipaSelf' => ['name' => 'Ivan Lipa', 'identifier' => 'IVAN LIPA'],
            'lipaDot' => ['name' => 'Ivan Lipa.'],
            'erd' => ['name' => 'Marko Erd', 'reviewed' => true],
            'erdChild' => ['name' => 'Sme - Marko', 'parents' => ['erd']],
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
        ]);
        $this->linkAssets('jakubco', 2);
        $this->linkAssets('jakubcoDash', 1);
        $this->linkAssets('novakSport', 1);
        $this->linkAssets('lipaSelf', 1);
        $this->linkAssets('tatranMain', 1);

        $this->file = (string) tempnam(sys_get_temp_dir(), 'author-merge-plan');
        $commandTester = new CommandTester((new Application(self::$kernel))->find(self::COMMAND));
        self::assertSame(Command::SUCCESS, $commandTester->execute(['--ext-system' => 'cms', '--file' => $this->file]));

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

    public function testFileIsUtf8WithBomForExcel(): void
    {
        self::assertStringStartsWith("\u{FEFF}" . implode(';', AuthorMergeCsv::COLUMNS), (string) file_get_contents($this->file));
    }

    public function testMainIsTheVariantWithMostAssets(): void
    {
        $this->assertGroup(['jakubco' => 'hlavny', 'jakubcoDash' => 'podriadeny', 'jakubcoSpace' => 'podriadeny'], 'A3 schválený je variant s menej fotkami');
        self::assertSame('2', $this->rows['jakubco'][AuthorMergeCsv::ASSET_COUNT]);
        self::assertSame((string) $this->authors['jakubco']->getId(), $this->rows['jakubcoSpace'][AuthorMergeCsv::CURRENT_AUTHOR_IDS]);
        self::assertSame('1', $this->rows['jakubco'][AuthorMergeCsv::GROUP_REVIEWED_COUNT]);
    }

    public function testDifferentIdentifiersAreNeverGrouped(): void
    {
        $this->assertGroup(['novakSport' => 'hlavny', 'novakSportDot' => 'podriadeny'], 'A1 nikto nie je schválený');
        self::assertArrayNotHasKey('novakPhoto', $this->rows);
        self::assertSame('sport', $this->rows['novakSport'][AuthorMergeCsv::GROUP_IDENTIFIER]);
    }

    public function testIdentifierEqualToNameCountsAsNone(): void
    {
        $this->assertGroup(['lipaSelf' => 'hlavny', 'lipaDot' => 'podriadeny'], 'A1 nikto nie je schválený');
        self::assertSame('', $this->rows['lipaSelf'][AuthorMergeCsv::GROUP_IDENTIFIER]);
    }

    public function testCurrentAuthorIsMainAndItsChildrenStay(): void
    {
        $this->assertGroup(['erd' => 'hlavny', 'erdDot' => 'podriadeny', 'erdMapped' => 'bez zmeny'], 'A5 hlavný už je nadriadeným iných');
        self::assertSame('1', $this->rows['erdMapped'][AuthorMergeCsv::CURRENT_AUTHOR_COUNT]);
        self::assertSame('2', $this->rows['erd'][AuthorMergeCsv::CHILD_AUTHOR_COUNT]);
    }

    public function testGroupsTheRulesCannotDecideGoToPeopleFirst(): void
    {
        $this->assertGroup(['kovacA' => '', 'kovacB' => ''], 'M3 dvaja nadriadení v jednej skupine');
        $this->assertGroup(['hraskoPlain' => '', 'hraskoCaron' => ''], 'M1 iniciály sa líšia len diakritikou');
        self::assertSame('ručne', $this->rows['kovacA'][AuthorMergeCsv::PROPOSAL]);
        self::assertLessThan((int) $this->rows['jakubco'][AuthorMergeCsv::GROUP], (int) $this->rows['kovacA'][AuthorMergeCsv::GROUP]);
        self::assertLessThan((int) $this->rows['jakubco'][AuthorMergeCsv::GROUP], (int) $this->rows['hraskoCaron'][AuthorMergeCsv::GROUP]);
        self::assertArrayNotHasKey('alone', $this->rows);
    }

    public function testCurrentAuthorsOfTheMainAuthorGoToTheVariants(): void
    {
        $this->assertGroup(['burcikMain' => 'hlavny', 'burcikVariant' => 'podriadeny'], 'A6 člen už má nadriadených od človeka');
        self::assertEqualsCanonicalizing(['SME', 'Matúš Burčík'], explode(' + ', $this->rows['burcikVariant'][AuthorMergeCsv::CURRENT_AUTHOR_NAMES]));
    }

    public function testCurrentAuthorsThatDropAPartOfTheNameGoToPeople(): void
    {
        $this->assertGroup(['korzarMain' => '', 'korzarVariant' => ''], 'M4 nadriadení nepokrývajú meno');
        $this->assertGroup(['tatranMain' => 'hlavny', 'tatranMapped' => 'bez zmeny'], 'A7 skupina je už upratená');
    }

    /**
     * @param array<string, string> $decisions all members of one group, the main author first
     */
    private function assertGroup(array $decisions, string $variant): void
    {
        $keys = array_keys($decisions);
        $group = $this->rows[$keys[0]][AuthorMergeCsv::GROUP];
        $members = array_keys(array_filter($this->rows, static fn (array $row): bool => $row[AuthorMergeCsv::GROUP] === $group));
        self::assertSame($keys, $members);
        foreach ($decisions as $key => $decision) {
            self::assertSame($decision, $this->rows[$key][AuthorMergeCsv::DECISION], $key);
            self::assertSame($variant, $this->rows[$key][AuthorMergeCsv::VARIANT], $key);
            self::assertSame('cms', $this->rows[$key][AuthorMergeCsv::EXT_SYSTEM], $key);
        }
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
