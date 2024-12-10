<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use Doctrine\Common\Collections\Collection;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class AuthorCleanPhraseWordCache extends AbstractManager
{
    public const string PHRASE_ID_PREFIX = '_phrase_id_';
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

    public static function getCacheKey(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode): string
    {
        return $type->value . '_' . $mode->value;
    }

    // word remove
    // regex remove
    // word split
    // word replace

    // todo validate cache key combination
    public function getList(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode): array
    {
        $item = $this->coreDamBundleAuthorCleanPhraseCache->getItem(self::getCacheKey($type, $mode));

        if ($item->isHit()) {
            return $item->get();
        }

        return $this->buildCache($type, $mode, $item);
    }

    private function buildCache(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode, CacheItemInterface $item): array
    {
        $phrases = $this->repository->findAllByTypeAndMode($type, $mode);

        $regexes = array_map(
            fn (array $phrases): string => $this->buildSingleRegex($phrases),
            $type->is(AuthorCleanPhraseType::Word)
                ? $this->groupPhrases($phrases)
                : $phrases->map(fn (AuthorCleanPhrase $phrase): array => [$phrase])->getValues()
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
            '%s%s%sui',
            self::DELIMITER,
            implode(
                '|',
                array_map(
                    fn (AuthorCleanPhrase $phrase): string =>
                    sprintf(
                        '(?P<%s>%s)',
                        self::PHRASE_ID_PREFIX . $phrase->getId(),
                        $phrase->getType()->is(AuthorCleanPhraseType::Regex)
                            ? $phrase->getPhrase()
                            : preg_quote($phrase->getPhrase(), self::DELIMITER)
                    ),
                    $phrases
                )
            ),
            self::DELIMITER
        );
    }
}
