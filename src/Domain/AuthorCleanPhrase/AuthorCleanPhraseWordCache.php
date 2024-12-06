<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use Doctrine\Common\Collections\Collection;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class AuthorCleanPhraseWordCache extends AbstractManager
{
    private const int MAX_REGEX_LENGTH = 2000;
    private const string DELIMITER = '~';
    private int $maxSingleRegexLength = self::MAX_REGEX_LENGTH;

    public function __construct(
        private readonly AuthorCleanPhraseRepository $repository,
        private readonly CacheItemPoolInterface $coreDamBundleAuthorCleanPhraseCache
    ) {
    }

    public function setMaxSingleRegexLength(int $maxSingleRegexLength): self
    {
        $this->maxSingleRegexLength = $maxSingleRegexLength;

        return $this;
    }

    public function getList(AuthorCleanPhraseMode $mode): array
    {
        // TODO only specific types can be cached
        $item = $this->coreDamBundleAuthorCleanPhraseCache->getItem($mode->value);

        if ($item->isHit()) {
            return $item->get();
        }

        return $this->buildCache($mode, $item);
    }

    private function buildCache(AuthorCleanPhraseMode $mode, CacheItemInterface $item): array
    {
        $phrases = $this->repository->findAllByTypeAndMode(AuthorCleanPhraseType::Word, $mode);

        $regexes = array_map(
            fn (array $phrases): string => $this->buildSingleRegex($phrases),
            $this->groupPhrases($phrases)
        );

        $item->set($regexes);
        $this->coreDamBundleAuthorCleanPhraseCache->save($item);

        return $regexes;
    }

    /**
     * @param Collection<int, AuthorCleanPhrase> $phrases
     *
     * @return array<int, array<int, AuthorCleanPhrase>>
     */
    private function groupPhrases(Collection $phrases): array
    {
        $phrasesGroups = [];
        $lastGroup = [];
        $lastGroupSum = 0;

        foreach ($phrases as $phrase) {
            // todo MBSTR vs NON MB STR
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

    /**
     * @param array<int, AuthorCleanPhrase> $phrases
     */
    private function buildSingleRegex(array $phrases): string
    {
        if (empty($phrases)) {
            return '';
        }

        return sprintf(
            '%s(%s)%sui',
            self::DELIMITER,
            implode(
                '|',
                array_map(
                    fn (AuthorCleanPhrase $phrase): string => preg_quote($phrase->getPhrase(), self::DELIMITER),
                    $phrases
                )
            ),
            self::DELIMITER
        );
    }
}
