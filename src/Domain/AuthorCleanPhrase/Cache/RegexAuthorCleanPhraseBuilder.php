<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;

final class RegexAuthorCleanPhraseBuilder extends AbstractAuthorCleanPhraseBuilder
{
    /**
     * @throws AuthorCleanPhraseException
     */
    public function buildCache(AuthorCleanPhraseMode $mode, ExtSystem $extSystem): array
    {
        // Replace maps a matched phrase onto a replacement author through the named group the word
        // builder emits; a hand-written regex has no such mapping. Remove and split only need the
        // pattern itself, and a separator like "a dash with a space beside it" is not expressible
        // as a quoted word.
        if ($mode->is(AuthorCleanPhraseMode::Replace)) {
            throw new AuthorCleanPhraseException(
                message: AuthorCleanPhraseException::ERROR_INVALID_MODE_AND_COMBINATION,
                detail: 'Regex type supports remove and split modes only. Mode ' . $mode->value . ' is not valid.'
            );
        }

        $phrases = $this->repository->findAllByTypeAndMode(AuthorCleanPhraseType::Regex, $mode, $extSystem);

        return $phrases->map(
            fn (AuthorCleanPhrase $phrase): string => $this->buildSingleRegex([$phrase])
        )->getValues();
    }

    public static function getDefaultKeyName(): string
    {
        return AuthorCleanPhraseType::Regex->value;
    }
}
