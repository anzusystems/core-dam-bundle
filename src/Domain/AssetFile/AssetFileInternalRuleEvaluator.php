<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\DamUser;

final readonly class AssetFileInternalRuleEvaluator
{
    public function evaluate(Asset $asset, AssetFile $assetFile): ?bool
    {
        if ($assetFile->getFlags()->isOverrideInternal()) {
            return null;
        }

        $internalRule = $assetFile->getLicence()->getInternalRule();

        if (false === $internalRule->isActive()) {
            return null;
        }

        $markAsInternalSince = $internalRule->getMarkAsInternalSince();
        if (null !== $markAsInternalSince && $assetFile->getCreatedAt() < $markAsInternalSince) {
            return false;
        }

        $ruleAuthors = $assetFile->getLicence()->getInternalRuleAuthors();
        if ($ruleAuthors->count() > 0) {
            foreach ($asset->getAuthors() as $author) {
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

    public function evaluateAndApply(Asset $asset, AssetFile $assetFile): void
    {
        $result = $this->evaluate($asset, $assetFile);
        if (null !== $result) {
            $assetFile->getFlags()->setInternal($result);
        }
    }
}
