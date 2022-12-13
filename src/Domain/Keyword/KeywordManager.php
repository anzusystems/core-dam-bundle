<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Keyword;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Keyword;

final class KeywordManager extends AbstractManager
{
    public function create(Keyword $keyword, bool $flush = true): Keyword
    {
        $this->trackCreation($keyword);
        $this->entityManager->persist($keyword);
        $this->flush($flush);

        return $keyword;
    }

    public function update(Keyword $keyword, Keyword $newKeyword, bool $flush = true): Keyword
    {
        $this->trackModification($keyword);
        $keyword
            ->setName($newKeyword->getName())
            ->setFlags($newKeyword->getFlags())
        ;
        $this->flush($flush);

        return $keyword;
    }

    public function delete(Keyword $keyword, bool $flush = true): bool
    {
        $this->entityManager->remove($keyword);
        $this->flush($flush);

        return true;
    }
}
