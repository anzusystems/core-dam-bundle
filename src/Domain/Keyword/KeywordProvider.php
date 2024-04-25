<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Keyword;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;

final readonly class KeywordProvider
{
    public function __construct(
        private KeywordRepository $repository,
        private KeywordManager $keywordManager,
    ) {
    }

    public function provideKeyword(string $title, ExtSystem $extSystem, bool $flush = true): ?Keyword
    {
        $title = StringHelper::parseString(input: $title, length: Keyword::NAME_MAX_LENGTH);
        if (empty($title)) {
            return null;
        }

        $keyword = $this->repository->findOneByNameAndExtSystem(
            name: $title,
            extSystem: $extSystem
        );

        if ($keyword) {
            return $keyword;
        }

        return $this->keywordManager->create(
            keyword: (new Keyword())
                ->setExtSystem($extSystem)
                ->setName($title),
            flush: $flush
        );
    }
}
