<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorProviderTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private AuthorProvider $authorProvider;
    private ExtSystem $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorProvider = $this->getService(AuthorProvider::class);
        $cms = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->cms = $cms;
    }

    /**
     * @param array<string, array{name: string, reviewed?: bool, identifier?: string, parents?: list<string>}> $tree
     * @param list<string> $resolved acceptable keys, empty = null
     * @param list<string> $existing acceptable keys for findExisting(), empty = null
     */
    #[DataProvider('resolveDataProvider')]
    public function testResolve(array $tree, string $input, string $identifier, array $resolved, array $existing): void
    {
        $authors = $this->createAuthorTree($this->cms, $tree);

        $this->assertResolvedTo($authors, $resolved, $this->authorProvider->resolve($input, $this->cms, $identifier)?->getId());
        $this->assertResolvedTo($authors, $existing, $this->authorProvider->findExisting($input, $this->cms, $identifier)?->getId());
    }

    public static function resolveDataProvider(): iterable
    {
        $sita = [
            'sita' => [
                'name' => "\u{AD}SITA",
                'reviewed' => true,
            ],
            'tasr' => [
                'name' => 'TASR',
                'reviewed' => true,
            ],
            'fero' => [
                'name' => 'Fero Kováč',
            ],
        ];

        yield 'exact: single root, case and accent insensitive' => [
            ['kuchta' => ['name' => 'Gabriel Kuchta']], 'GABRIEL KUCHTA', '', ['kuchta'], ['kuchta'],
        ];
        yield 'exact: single child is returned whole' => [
            ['root' => ['name' => 'Jozef Jakubčo', 'reviewed' => true], 'child' => ['name' => 'Jozef Jakubčo.', 'parents' => ['root']]],
            'Jozef Jakubčo.', '', ['child'], ['child'],
        ];
        yield 'exact: twins by identifier, the reviewed one wins (TASR [] vs TASR [TASR])' => [
            ['plain' => ['name' => 'TASR'], 'reviewed' => ['name' => 'TASR', 'identifier' => 'TASR', 'reviewed' => true]],
            'TASR', '', ['reviewed'], ['reviewed'],
        ];
        yield 'exact: accent twins, the reviewed one wins (Šimícek vs Šimíček)' => [
            ['typo' => ['name' => 'Vladimír Šimícek', 'identifier' => 'Vladimír Šimícek'], 'reviewed' => ['name' => 'Vladimír Šimíček', 'reviewed' => true]],
            'Vladimir Simicek', '', ['reviewed'], ['reviewed'],
        ];
        yield 'exact: two reviewed namesakes are not decided, create falls back to a reviewed one' => [
            ['photo' => ['name' => 'Peter Novák', 'identifier' => 'foto', 'reviewed' => true], 'sport' => ['name' => 'Peter Novák', 'identifier' => 'šport', 'reviewed' => true], 'plain' => ['name' => 'Peter Novák']],
            'Peter Novák', '', [], ['photo', 'sport'],
        ];
        yield 'exact: two unreviewed twins are not decided, create falls back to one of them' => [
            ['first' => ['name' => 'Mitja Kobal'], 'second' => ['name' => 'MITJA KOBAL', 'identifier' => 'MITJA KOBAL']],
            'mitja kobal', '', [], ['first', 'second'],
        ];
        yield 'exact: a reviewed root and a child of another reviewed root are not decided (SITA vs sita → ­SITA)' => [
            ['softSita' => ['name' => "\u{AD}SITA", 'reviewed' => true], 'adminSita' => ['name' => 'SITA', 'reviewed' => true], 'child' => ['name' => 'sita', 'identifier' => 'sita', 'parents' => ['softSita']]],
            'sita', '', [], ['adminSita'],
        ];
        yield 'exact: wins over a reviewed normalized variant' => [
            ['dot' => ['name' => 'Marko Erd.'], 'reviewed' => ['name' => 'Marko Erd', 'reviewed' => true]],
            'Marko Erd.', '', ['dot'], ['dot'],
        ];
        yield 'exact: child with an unreviewed current author is returned whole (SITA/TASR/Fero)' => [
            [...$sita, 'child' => ['name' => 'SITA/TASR/Fero Kováč', 'parents' => ['sita', 'tasr', 'fero']]],
            'SITA/TASR/Fero Kováč', '', ['child'], ['child'],
        ];
        yield 'exact: identifier filters the twins' => [
            ['plain' => ['name' => 'TASR'], 'reviewed' => ['name' => 'TASR', 'identifier' => 'TASR', 'reviewed' => true]],
            'TASR', 'TASR', ['reviewed'], ['reviewed'],
        ];

        yield 'normalized: case, diacritics and trailing punctuation' => [
            ['kuchta' => ['name' => 'Gabriel Kuchta', 'reviewed' => true]], 'GABRIEL KUCHTA,', '', ['kuchta'], ['kuchta'],
        ];
        yield 'normalized: soft hyphen and leading dash (­SITA vs - SITA)' => [
            ['sita' => ['name' => "\u{AD}SITA", 'reviewed' => true]], '– SITA', '', ['sita'], ['sita'],
        ];
        yield 'normalized: copyright sign and double space' => [
            ['tasr' => ['name' => 'TASR/Milan Kapusta']], '© TASR / Milan  Kapusta', '', ['tasr'], ['tasr'],
        ];
        yield 'normalized: emoji and spaces around a slash (social media credit)' => [
            ['x' => ['name' => 'X / サッカー日本代表 🇯🇵']], 'X/サッカー日本代表', '', ['x'], ['x'],
        ];
        yield 'normalized: a reviewed root and its children give the root (Jakubčo)' => [
            ['root' => ['name' => 'Jozef Jakubčo', 'reviewed' => true], 'dot' => ['name' => 'Jozef Jakubčo.', 'parents' => ['root']], 'space' => ['name' => 'Jozef  Jakubčo', 'parents' => ['root']]],
            'JOZEF JAKUBČO,', '', ['root'], ['root'],
        ];
        yield 'normalized: an unreviewed root and its children give the root (after the merge)' => [
            ['root' => ['name' => 'Jan Novak'], 'variant' => ['name' => 'Ján  Novák.', 'parents' => ['root']]],
            'JAN NOVAK,', '', ['root'], ['root'],
        ];
        yield 'normalized: children of the same current authors give one of them' => [
            ['parent' => ['name' => 'G. Kuchta', 'reviewed' => true], 'dot' => ['name' => 'Gabriel Kuchta.', 'parents' => ['parent']], 'space' => ['name' => 'Gabriel  Kuchta', 'parents' => ['parent']]],
            'gabriel kuchta,', '', ['dot', 'space'], ['dot', 'space'],
        ];
        yield 'normalized: the single reviewed variant among unreviewed ones' => [
            ['reviewed' => ['name' => 'Vladimír Šimíček', 'reviewed' => true], 'upper' => ['name' => 'VLADIMÍR  ŠIMÍČEK'], 'dash' => ['name' => '- VLADIMÍR ŠIMÍČEK']],
            'Vladimír Šimíček.', '', ['reviewed'], ['reviewed'],
        ];
        yield 'normalized: two reviewed variants are not decided (TASR/AP)' => [
            ['space' => ['name' => 'TASR/AP ', 'identifier' => 'TASR/AP', 'reviewed' => true], 'agency' => ['name' => 'TASR/AP', 'identifier' => 'TASR ', 'reviewed' => true], 'dot' => ['name' => 'TASR/AP.']],
            'TASR / AP;', '', [], [],
        ];
        yield 'normalized: only unreviewed variants are not decided' => [
            ['upper' => ['name' => 'MITJA KOBAL'], 'space' => ['name' => 'Mitja  Kobal']],
            'Mitja Kobal.', '', [], [],
        ];
        yield 'normalized: a child whose current authors are all reviewed wins over an unreviewed variant' => [
            ['agency' => ['name' => 'Agentúra', 'reviewed' => true], 'photographer' => ['name' => 'Fotograf', 'reviewed' => true], 'child' => ['name' => 'Agentúra / Fotograf,', 'parents' => ['agency', 'photographer']], 'variant' => ['name' => 'Agentúra/Fotograf.']],
            'agentura/fotograf', '', ['child'], ['child'],
        ];
        yield 'normalized: a child with an unreviewed current author and an unreviewed variant are not decided' => [
            [...$sita, 'child' => ['name' => 'SITA/TASR/Fero Kováč', 'parents' => ['sita', 'tasr', 'fero']], 'variant' => ['name' => 'SITA / TASR / Fero Kováč.']],
            'sita/tasr/fero kovac;', '', [], [],
        ];
        yield 'normalized: a single child with an unreviewed current author is returned whole' => [
            [...$sita, 'child' => ['name' => 'SITA/TASR/Fero Kováč', 'parents' => ['sita', 'tasr', 'fero']]],
            'SITA / TASR / Fero Kováč.', '', ['child'], ['child'],
        ];
        yield 'normalized: children of different reviewed current authors are not decided' => [
            ['photo' => ['name' => 'Peter Novák foto', 'reviewed' => true], 'sport' => ['name' => 'Peter Novák šport', 'reviewed' => true], 'photoChild' => ['name' => 'Peter Novák.', 'parents' => ['photo']], 'sportChild' => ['name' => 'Peter  Novák', 'parents' => ['sport']]],
            'peter novak,', '', [], [],
        ];
        yield 'normalized: identifier filters the variants' => [
            ['photo' => ['name' => 'Peter Novák', 'identifier' => 'foto'], 'sport' => ['name' => 'Peter Novák.', 'identifier' => 'šport', 'reviewed' => true]],
            'Peter Novak,', 'foto', ['photo'], ['photo'],
        ];
        yield 'normalized: without an identifier the reviewed namesake wins' => [
            ['photo' => ['name' => 'Peter Novák', 'identifier' => 'foto'], 'sport' => ['name' => 'Peter Novák.', 'identifier' => 'šport', 'reviewed' => true]],
            'Peter Novak,', '', ['sport'], ['sport'],
        ];
        yield 'normalized: a name of punctuation only never matches' => [
            ['dots' => ['name' => '...']], '..', '', [], [],
        ];
        yield 'nothing similar' => [
            ['kuchta' => ['name' => 'Gabriel Kuchta', 'reviewed' => true]], 'Gabriela Kuchtová', '', [], [],
        ];
    }

    public function testOtherExtSystemIsIgnored(): void
    {
        $blog = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_BLOG);
        self::assertInstanceOf(ExtSystem::class, $blog);
        $this->createAuthorTree($blog, ['kuchta' => ['name' => 'Gabriel Kuchta', 'reviewed' => true]]);

        self::assertNull($this->authorProvider->resolve('Gabriel Kuchta', $this->cms));
        self::assertNull($this->authorProvider->resolve('Gabriel Kuchta.', $this->cms));
    }

    /**
     * @param array<string, array{name: string, reviewed?: bool, identifier?: string, parents?: list<string>}> $tree
     * @param list<string> $onAsset
     * @param list<string> $expected
     */
    #[DataProvider('currentAuthorsDataProvider')]
    public function testProvideCurrentAuthorToColl(array $tree, array $onAsset, bool $changed, array $expected): void
    {
        $authors = $this->createAuthorTree($this->cms, $tree);
        $asset = new Asset();
        foreach ($onAsset as $key) {
            $asset->addAuthor($authors[$key]);
        }

        self::assertSame($changed, $this->authorProvider->provideCurrentAuthorToColl($asset));
        self::assertSame($expected, $this->toTreeKeys($authors, $asset->getAuthors()));
    }

    public static function currentAuthorsDataProvider(): iterable
    {
        $tree = [
            'agency' => ['name' => 'Agentúra', 'reviewed' => true],
            'photographer' => ['name' => 'Fotograf'],
            'child' => ['name' => 'Agentúra / Fotograf', 'parents' => ['agency', 'photographer']],
            'root' => ['name' => 'Jozef Jakubčo', 'reviewed' => true],
            'reviewedChild' => ['name' => 'Tatran vydavateľstvo', 'reviewed' => true, 'parents' => ['root']],
        ];

        yield 'roots stay' => [$tree, ['agency', 'root'], false, ['agency', 'root']];
        yield 'child is replaced by all its current authors, also an unreviewed one' => [$tree, ['child'], true, ['agency', 'photographer']];
        yield 'current author already on the asset is not added twice' => [$tree, ['agency', 'child'], true, ['agency', 'photographer']];
        yield 'reviewed child from old data is replaced too' => [$tree, ['reviewedChild'], true, ['root']];
        yield 'several children and roots together' => [$tree, ['child', 'reviewedChild', 'root'], true, ['agency', 'photographer', 'root']];
    }

    /**
     * @param array<string, Author> $authors
     * @param list<string> $acceptableKeys
     */
    private function assertResolvedTo(array $authors, array $acceptableKeys, ?string $authorId): void
    {
        if ([] === $acceptableKeys) {
            self::assertNull($authorId);

            return;
        }

        $acceptableIds = array_map(static fn (string $key): string => (string) $authors[$key]->getId(), $acceptableKeys);
        self::assertContains($authorId, $acceptableIds, sprintf('Expected one of: %s', implode(', ', $acceptableKeys)));
    }
}
