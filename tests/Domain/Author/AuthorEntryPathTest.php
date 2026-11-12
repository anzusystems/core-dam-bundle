<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion\AuthorSuggester;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorFacade;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\AuthorExistsException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every path that turns a name into an author of an asset: adm create, sys API, EXIF with the clean phrases.
 * TTS is covered in TtsSynthesisFunctionalTest, the matching rules themselves in AuthorProviderTest.
 */
final class AuthorEntryPathTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const array TREE = [
        'kuchta' => [
            'name' => 'Gabriel Kuchta',
            'reviewed' => true,
        ],
        'kuchtaDot' => [
            'name' => 'Gabriel Kuchta.',
        ],
        'jakubco' => [
            'name' => 'Jozef Jakubčo',
            'reviewed' => true,
        ],
        'jakubcoDot' => [
            'name' => 'Jozef Jakubčo.',
            'parents' => ['jakubco'],
        ],
        'tasr' => ['name' => 'TASR'],
        'tasrReviewed' => ['name' => 'TASR', 'identifier' => 'TASR', 'reviewed' => true],
        'tatran' => ['name' => 'Tatran', 'reviewed' => true],
        'tatranChild' => ['name' => 'Tatran vydavateľstvo', 'parents' => ['tatran']],
        'agency' => ['name' => 'Agentúra', 'reviewed' => true],
        'photographer' => ['name' => 'Fotograf'],
        'agencyPhotographer' => ['name' => 'Agentúra / Fotograf', 'parents' => ['agency', 'photographer']],
        'novakPhoto' => ['name' => 'Peter Novák', 'identifier' => 'foto', 'reviewed' => true],
        'novakSport' => ['name' => 'Peter Novák', 'identifier' => 'šport', 'reviewed' => true],
        'kobal' => ['name' => 'MITJA KOBAL'],
        'erd' => ['name' => 'Marko Erd', 'reviewed' => true],
        'erdSpace' => ['name' => 'Marko  Erd', 'identifier' => 'archív', 'reviewed' => true],
    ];

    private ExtSystem $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $cms = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->cms = $cms;
    }

    /**
     * Adm `POST /author`: a found author is answered through AuthorExistsException, otherwise a new one is created.
     */
    #[DataProvider('admCreateDataProvider')]
    public function testAdmCreate(string $name, string $identifier, string $expected): void
    {
        $authors = $this->createAuthorTree($this->cms, self::TREE);
        $author = (new Author())->setName($name)->setIdentifier($identifier)->setExtSystem($this->cms);

        try {
            $created = $this->getService(AuthorFacade::class)->create($author);
            $result = 'name:' . $created->getName();
        } catch (AuthorExistsException $exception) {
            $result = $this->toTreeKeys($authors, new ArrayCollection([$exception->getExistingAuthor()]))[0];
        }

        self::assertSame($expected, $result);
    }

    public static function admCreateDataProvider(): iterable
    {
        yield 'normalized variant gives the reviewed author' => ['GABRIEL  KUCHTA', '', 'kuchta'];
        yield 'exact child is returned, the asset save replaces it' => ['Jozef Jakubčo.', '', 'jakubcoDot'];
        yield 'exact twins give the reviewed one' => ['tasr', '', 'tasrReviewed'];
        yield 'normalized child gives the child' => ['agentura/fotograf', '', 'agencyPhotographer'];
        yield 'two reviewed variants: a new author' => ['Marko Erd,', '', 'name:Marko Erd,'];
        yield 'identifier given: a namesake with another identifier is created' => ['Gabriel Kuchta', 'redaktor', 'name:Gabriel Kuchta'];
        yield 'nothing similar: a new author' => ['Gabriela Kuchtová', '', 'name:Gabriela Kuchtová'];
    }

    public function testAdmCreateOfTwoReviewedNamesakesReturnsOneOfThem(): void
    {
        $authors = $this->createAuthorTree($this->cms, self::TREE);

        try {
            $this->getService(AuthorFacade::class)->create((new Author())->setName('Peter Novák')->setExtSystem($this->cms));
            self::fail('An existing author was expected.');
        } catch (AuthorExistsException $exception) {
            self::assertContains($exception->getExistingAuthor(), [$authors['novakPhoto'], $authors['novakSport']]);
        }
    }

    /**
     * Sys API (CMS import by URL): each name is resolved or created, then children are replaced by their current authors.
     *
     * @param list<string> $names
     * @param list<string> $expected
     */
    #[DataProvider('sysAuthorsDataProvider')]
    public function testSysAuthors(array $names, array $expected): void
    {
        $authors = $this->createAuthorTree($this->cms, self::TREE);
        $image = $this->findCmsImage();

        $this->getService(AssetSysFacade::class)->setupAuthors($image, (new AssetFileSysUrlCreateDto())->setAuthors($names));

        self::assertSame($expected, $this->toTreeKeys($authors, $image->getAsset()->getAuthors()));
    }

    public static function sysAuthorsDataProvider(): iterable
    {
        yield 'child with an unreviewed current author gives both' => [['Agentúra/Fotograf.'], ['agency', 'photographer']];
        yield 'exact child is replaced by its root' => [['Jozef Jakubčo.'], ['jakubco']];
        yield 'normalized variant of a reviewed root' => [['GABRIEL KUCHTA;'], ['kuchta']];
        yield 'exact twins give the reviewed one' => [['TASR'], ['tasrReviewed']];
        yield 'two reviewed variants: a new author' => [['Marko Erd,'], ['name:Marko Erd,']];
        yield 'current author also named directly is not added twice' => [['Agentúra', 'Agentúra / Fotograf'], ['agency', 'photographer']];
        yield 'several credits together' => [['Tatran vydavateľstvo', 'Gabriel Kuchta.', 'Fotograf'], ['kuchtaDot', 'photographer', 'tatran']];
        yield 'empty name is skipped' => [['  '], []];
    }

    /**
     * EXIF: the clean phrases split and clean the credit, each part is resolved, children are replaced.
     * Clean phrases of cms in the fixtures: split `/` and `;`, remove `©`, `(c)`, `photo`, e-mail and phone,
     * replace `AgencyA -` and `AgencyA-` by the fixture author AgencyA.
     *
     * @param list<string> $expected
     * @param array<string, int> $suggestionCounts
     */
    #[DataProvider('exifDataProvider')]
    public function testExifAuthors(string $artist, array $expected, array $suggestionCounts): void
    {
        $authors = $this->createAuthorTree($this->cms, self::TREE);
        $image = $this->findCmsImage();
        $suggester = $this->getService(AuthorSuggester::class);

        $suggester->applySuggestions($image, $suggester->provideSuggestions($image, ['Artist' => $artist]));

        self::assertSame($expected, $this->toTreeKeys($authors, $image->getAsset()->getAuthors()));
        self::assertSame(
            $suggestionCounts,
            array_map(count(...), $image->getAsset()->getMetadata()->getAuthorSuggestions()),
        );
    }

    public static function exifDataProvider(): iterable
    {
        yield 'copyright, split, a variant and an exact child' => [
            '© Gabriel Kuchta. / Jozef Jakubčo.',
            ['jakubco', 'kuchtaDot'],
            ['Gabriel Kuchta.' => 1, 'Jozef Jakubčo.' => 1],
        ];
        yield 'a normalized variant goes to the reviewed root' => [
            'GABRIEL  KUCHTA;Tatran vydavateľstvo',
            ['kuchta', 'tatran'],
            ['GABRIEL  KUCHTA' => 1, 'Tatran vydavateľstvo' => 1],
        ];
        yield 'exact twins give the reviewed one (was a suggestion only)' => [
            'TASR',
            ['tasrReviewed'],
            ['TASR' => 1],
        ];
        yield 'two reviewed namesakes stay suggestions' => [
            'Peter Novák',
            [],
            ['Peter Novák' => 2],
        ];
        yield 'replace phrase gives the fixture author, the rest is resolved' => [
            'AgencyA - Mitja  Kobal',
            ['kobal', 'name:AgencyA'],
            ['Mitja  Kobal' => 1],
        ];
        yield 'remove phrases drop a word and an e-mail' => [
            'photo Marko Erd marko@sme.sk',
            ['erd'],
            ['Marko Erd' => 1],
        ];
        yield 'two reviewed variants: a new author' => [
            'Marko Erd,',
            ['name:Marko Erd,'],
            ['Marko Erd,' => 1],
        ];
        yield 'a split credit gives the roots, not the child' => [
            'Agentúra/Fotograf',
            ['agency', 'photographer'],
            ['Agentúra' => 1, 'Fotograf' => 1],
        ];
    }

    private function findCmsImage(): ImageFile
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        self::assertInstanceOf(ImageFile::class, $image);
        self::assertSame(ExtSystemFixtures::ID_CMS, $image->getExtSystem()->getId());
        $image->getAsset()->getAuthors()->clear();
        $image->getAsset()->getMetadata()->setAuthorSuggestions([]);

        return $image;
    }
}
