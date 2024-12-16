<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AuthorCleanPhraseBuilderInterface
{
    public function buildCache(AuthorCleanPhraseMode $mode, ExtSystem $extSystem): array;

    public static function getDefaultKeyName(): string;
}
