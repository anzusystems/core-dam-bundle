<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAuthorCleanPhraseBuilder implements AuthorCleanPhraseBuilderInterface
{
    public const string PHRASE_ID_PREFIX = '_phrase_id_';
    private const string WORD_BOUNDARY = '\s';
    private const string DELIMITER = '~';

    protected AuthorCleanPhraseRepository $repository;

    #[Required]
    public function setRepository(AuthorCleanPhraseRepository $repository): void
    {
        $this->repository = $repository;
    }

    public static function getCacheKey(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode, ExtSystem $extSystem): string
    {
        return $type->value . '_' . $mode->value . '_' . $extSystem->getSlug();
    }

    /**
     * @param array<int, AuthorCleanPhrase> $phrases
     */
    protected function buildSingleRegex(array $phrases, bool $isWordBoundary = false): string
    {
        if (empty($phrases)) {
            return '';
        }

        $regex = implode(
            '|',
            array_map(
                fn (AuthorCleanPhrase $phrase): string => sprintf(
                    '(?P<%s>%s)',
                    self::PHRASE_ID_PREFIX . $phrase->getId(),
                    $phrase->getType()->is(AuthorCleanPhraseType::Regex)
                        ? $phrase->getPhrase()
                        : preg_quote($phrase->getPhrase(), self::DELIMITER)
                ),
                $phrases
            )
        );

        return $isWordBoundary
            ? $this->buildWordBoundaryRegex($regex)
            : $this->buildRegex($regex);
    }

    private function buildRegex(string $phrase): string
    {
        return sprintf(
            '%s%s%sui',
            self::DELIMITER,
            $phrase,
            self::DELIMITER
        );
    }

    private function buildWordBoundaryRegex(string $phrase): string
    {
        return sprintf(
            '%s(^|%s)(%s)($|%s)%sui',
            self::DELIMITER,
            self::WORD_BOUNDARY,
            $phrase,
            self::WORD_BOUNDARY,
            self::DELIMITER
        );
    }
}
