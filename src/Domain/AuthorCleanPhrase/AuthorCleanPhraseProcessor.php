<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase\ProcessStringDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class AuthorCleanPhraseProcessor extends AbstractManager
{
    public function __construct(
        private readonly AuthorCleanPhraseWordCache $cleanPhraseWordCache,
        private readonly AuthorCleanPhraseRepository $repository,
    ) {
    }

    public function processString(string $string): ProcessStringDto
    {
        $authorParts = $this->split($string);
        $authorParts = $this->removeWords($authorParts);

        return $this->replace($string, $authorParts);
    }

    public function replace(string $string, array $authorParts): ProcessStringDto
    {
        $replace = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode:AuthorCleanPhraseMode::Replace
        );
        $repository = $this->repository;
        $authorIdReplacements = [];

        foreach ($authorParts as $index => $author) {
            $match = preg_replace_callback(
                $replace,
                function (array $matches) use ($repository, &$authorIdReplacements): string {
                    foreach ($matches as $key => $match) {
                        if (str_starts_with((string) $key, AuthorCleanPhraseWordCache::PHRASE_ID_PREFIX)) {
                            $id = (int) ltrim((string) $key, AuthorCleanPhraseWordCache::PHRASE_ID_PREFIX);
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

        return new ProcessStringDto(
            $string,
            $authorParts,
            CollectionHelper::newCollection($authorIdReplacements)
        );
    }

    /**
     * @param array<int, string> $strings
     */
    private function removeWords(array $strings): array
    {
        $wordRegexes = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode: AuthorCleanPhraseMode::Remove
        );
        $removeRegexes = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Regex,
            mode: AuthorCleanPhraseMode::Remove
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
     */
    private function split(string $string): array
    {
        $patterns = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode: AuthorCleanPhraseMode::Split
        );

        foreach ($patterns as $pattern) {
            return array_map('trim', preg_split($pattern, $string, -1, PREG_SPLIT_NO_EMPTY));
        }

        return [trim($string)];
    }
}
