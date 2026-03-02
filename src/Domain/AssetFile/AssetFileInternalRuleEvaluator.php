<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use DateTimeImmutable;

final readonly class AssetFileInternalRuleEvaluator
{
    public function evaluate(AssetFile $assetFile): ?bool
    {
        if ($assetFile->getFlags()->isOverrideInternal()) {
            return null;
        }

        $internalRule = $assetFile->getLicence()->getInternalRule();

        if (false === $internalRule->isActive()) {
            return null;
        }

        $markAsInternalSince = $internalRule->getMarkAsInternalSince();
        if ($markAsInternalSince instanceof DateTimeImmutable && $assetFile->getCreatedAt() < $markAsInternalSince) {
            return false;
        }

        $ruleAuthors = $assetFile->getLicence()->getInternalRuleAuthors();
        if ($ruleAuthors->count() > App::ZERO) {
            foreach ($assetFile->getAsset()->getAuthors() as $author) {
                if (false === $ruleAuthors->exists(fn (int $key, Author $ruleAuthor): bool => $ruleAuthor->getId() === $author->getId())) {
                    return false;
                }
            }
        }

        $ruleUsers = $assetFile->getLicence()->getInternalRuleUsers();
        if ($ruleUsers->count() > 0) {
            /** @var DamUser $createdBy */
            $createdBy = $assetFile->getCreatedBy();
            if (false === $ruleUsers->exists(fn (int $key, DamUser $ruleUser): bool => $ruleUser->getId() === $createdBy->getId())) {
                return false;
            }
        }

        return true;
    }

    public function evaluateAndApply(AssetFile $assetFile): void
    {
        $result = $this->evaluate($assetFile);
        if (null !== $result) {
            $assetFile->getFlags()->setInternal($result);
        }
    }
}
