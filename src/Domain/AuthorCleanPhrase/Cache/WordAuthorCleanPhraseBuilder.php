<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Doctrine\Common\Collections\Collection;

final class WordAuthorCleanPhraseBuilder extends AbstractAuthorCleanPhraseBuilder
{
    private const int MAX_REGEX_LENGTH = 2_000;

    private int $maxSingleRegexLength = self::MAX_REGEX_LENGTH;

    public function setMaxSingleRegexLength(int $maxSingleRegexLength): self
    {
        $this->maxSingleRegexLength = $maxSingleRegexLength;

        return $this;
    }

    public function buildCache(AuthorCleanPhraseMode $mode, ExtSystem $extSystem): array
    {
        $phrases = $this->repository->findAllByTypeAndMode(AuthorCleanPhraseType::Word, $mode, $extSystem, true);
        $regexes = $this->getRegexes($phrases, true);

        $phrases = $this->repository->findAllByTypeAndMode(AuthorCleanPhraseType::Word, $mode, $extSystem, false);

        return array_merge($regexes, $this->getRegexes($phrases, false));
    }

    public static function getDefaultKeyName(): string
    {
        return AuthorCleanPhraseType::Word->value;
    }

    private function getRegexes(Collection $phrases, bool $isWordBoundary): array
    {
        return array_map(
            fn (array $phrases): string => $this->buildSingleRegex($phrases, $isWordBoundary),
            $this->groupPhrases($phrases)
        );
    }

    /**
     * @param Collection<int, AuthorCleanPhrase> $phrases
     */
    private function groupPhrases(Collection $phrases): array
    {
        $phrasesGroups = [];
        $lastGroup = [];
        $lastGroupSum = 0;

        foreach ($phrases as $phrase) {
            $phraseLen = strlen($phrase->getPhrase());

            if ($lastGroupSum + $phraseLen > $this->maxSingleRegexLength) {
                $phrasesGroups[] = $lastGroup;
                $lastGroupSum = 0;
                $lastGroup = [];
            }

            $lastGroup[] = $phrase;
            $lastGroupSum += $phraseLen;
        }

        if (false === empty($lastGroup)) {
            $phrasesGroups[] = $lastGroup;
        }

        return $phrasesGroups;
    }
}
