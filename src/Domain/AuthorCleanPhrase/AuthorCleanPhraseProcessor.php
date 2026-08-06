<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AbstractAuthorCleanPhraseBuilder;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseCache;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase\AuthorCleanResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;

final class AuthorCleanPhraseProcessor extends AbstractManager
{
    /**
     * Upper bound for {@see reorderSurnameFirst}: "Smadišová, Stanislava" plus a middle name or a
     * compound surname still reads as one person, while a longer comma-joined phrase like
     * "Archív SME, redakcia denníka" is left verbatim instead of being silently rewritten.
     */
    private const int REORDER_MAX_WORDS = 3;

    /**
     * Hides commas from the split rules while {@see processString} runs with the surname-first
     * reading. Any character absent from credit data works; this one is never valid text.
     */
    private const string COMMA_PLACEHOLDER = "\u{0001}";

    public function __construct(
        private readonly AuthorCleanPhraseCache $cleanPhraseWordCache,
        private readonly AuthorCleanPhraseRepository $repository,
    ) {
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    /**
     * @param bool $commaReversesName how this source reads a comma: a separator between two credits
     *  (cms uploads: "SME, TASR") or the "Surname, Firstname" convention reversing one name
     *  (the NAXOS archive: "Smadišová, Stanislava"). Off by default — a caller that knows its source
     *  opts in, and the rules themselves stay shared.
     *
     * @throws AuthorCleanPhraseException
     */
    public function processString(string $string, ExtSystem $extSystem, bool $commaReversesName = false): AuthorCleanResultDto
    {
        $authorParts = $this->split(
            $commaReversesName ? str_replace(',', self::COMMA_PLACEHOLDER, $string) : $string,
            $extSystem,
        );
        $authorParts = $this->removeWords($authorParts, $extSystem);
        if ($commaReversesName) {
            $authorParts = array_map(
                static fn (string $part): string => self::reorderSurnameFirst(
                    str_replace(self::COMMA_PLACEHOLDER, ',', $part),
                ),
                $authorParts,
            );
        }

        return $this->replace($string, $authorParts, $extSystem);
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    public function replace(string $string, array $authorParts, ExtSystem $extSystem): AuthorCleanResultDto
    {
        $replace = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode: AuthorCleanPhraseMode::Replace,
            extSystem: $extSystem
        );
        $authorIdReplacements = [];

        foreach ($authorParts as $index => $author) {
            $match = preg_replace_callback(
                $replace,
                function (array $matches) use (&$authorIdReplacements): string {
                    foreach ($matches as $key => $match) {
                        if (str_starts_with((string) $key, AbstractAuthorCleanPhraseBuilder::PHRASE_ID_PREFIX)) {
                            $id = (int) ltrim((string) $key, AbstractAuthorCleanPhraseBuilder::PHRASE_ID_PREFIX);
                            $phrase = $this->repository->find($id);
                            if ($phrase instanceof AuthorCleanPhrase && $phrase->getAuthorReplacement()) {
                                $authorIdReplacements[(string) $phrase->getAuthorReplacement()->getId()] = $phrase->getAuthorReplacement();
                            }

                            break;
                        }
                    }

                    return '';
                },
                $author
            );

            if (is_string($match)) {
                $authorParts[$index] = trim($match);
            }
        }

        return new AuthorCleanResultDto(
            $string,
            array_unique(array_filter($authorParts)),
            CollectionHelper::newCollection($authorIdReplacements)
        );
    }

    /**
     * Applied by {@see processString} only under the surname-first reading, and public so a caller
     * doing its own part-level cleaning reverses names exactly the same way.
     */
    public static function reorderSurnameFirst(string $name): string
    {
        $parts = array_values(array_filter(
            array_map(trim(...), explode(',', $name)),
            static fn (string $part): bool => '' !== $part,
        ));
        if (2 !== count($parts)) {
            return $name;
        }

        $words = preg_split('~\s+~u', implode(' ', $parts), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return self::REORDER_MAX_WORDS < count($words) ? $name : $parts[1] . ' ' . $parts[0];
    }

    /**
     * @param array<int, string> $strings
     * @throws AuthorCleanPhraseException
     */
    private function removeWords(array $strings, ExtSystem $extSystem): array
    {
        $wordRegexes = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode: AuthorCleanPhraseMode::Remove,
            extSystem: $extSystem
        );
        $removeRegexes = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Regex,
            mode: AuthorCleanPhraseMode::Remove,
            extSystem: $extSystem
        );

        $regexes = [...$wordRegexes, ...$removeRegexes];
        $result = [];

        foreach ($strings as $string) {
            $res = trim(preg_replace($regexes, '', $string));
            if (false === ('' === $res)) {
                $result[] = $res;
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     * @throws AuthorCleanPhraseException
     */
    private function split(string $string, ExtSystem $extSystem): array
    {
        // Word and regex split phrases both apply, and every pattern splits what the previous ones
        // left — a separator expressible only as a regex (a dash that separates co-credits only with
        // a space beside it, unlike the one inside a double-barrelled surname) is a split rule like
        // any other.
        $patterns = [
            ...$this->cleanPhraseWordCache->getList(
                type: AuthorCleanPhraseType::Word,
                mode: AuthorCleanPhraseMode::Split,
                extSystem: $extSystem
            ),
            ...$this->cleanPhraseWordCache->getList(
                type: AuthorCleanPhraseType::Regex,
                mode: AuthorCleanPhraseMode::Split,
                extSystem: $extSystem
            ),
        ];

        $parts = [$string];
        foreach ($patterns as $pattern) {
            $split = [];
            foreach ($parts as $part) {
                $split = [...$split, ...(preg_split($pattern, $part) ?: [$part])];
            }
            $parts = $split;
        }

        return array_map('trim', $parts);
    }
}
