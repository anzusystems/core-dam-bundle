<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AbstractAuthorCleanPhraseBuilder;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseCache;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AuthorCleanPhrase\AuthorCleanResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;

final class AuthorCleanPhraseProcessor extends AbstractManager
{
    public function __construct(
        private readonly AuthorCleanPhraseCache $cleanPhraseWordCache,
        private readonly AuthorCleanPhraseRepository $repository,
    ) {
    }

    public function processString(string $string, ExtSystem $extSystem): AuthorCleanResultDto
    {
        $authorParts = $this->split($string, $extSystem);
        $authorParts = $this->removeWords($authorParts, $extSystem);

        return $this->replace($string, $authorParts, $extSystem);
    }

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
     * @param array<int, string> $strings
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
     */
    private function split(string $string, ExtSystem $extSystem): array
    {
        $patterns = $this->cleanPhraseWordCache->getList(
            type: AuthorCleanPhraseType::Word,
            mode: AuthorCleanPhraseMode::Split,
            extSystem: $extSystem
        );

        foreach ($patterns as $pattern) {
            return array_map('trim', preg_split($pattern, $string));
        }

        return [trim($string)];
    }
}
