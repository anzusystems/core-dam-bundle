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

        return $this->validateMarkAsInternalSince($assetFile)
            && $this->validateAuthors($assetFile)
            && $this->validateUsers($assetFile);
    }

    public function evaluateAndApply(AssetFile $assetFile): void
    {
        $result = $this->evaluate($assetFile);
        if (null !== $result) {
            $assetFile->getFlags()->setInternal($result);
        }
    }

    private function validateMarkAsInternalSince(AssetFile $assetFile): bool
    {
        $markAsInternalSince = $assetFile->getLicence()->getInternalRule()->getMarkAsInternalSince();

        if ($markAsInternalSince instanceof DateTimeImmutable && $assetFile->getCreatedAt() < $markAsInternalSince) {
            return false;
        }

        return true;
    }

    private function validateAuthors(AssetFile $assetFile): bool
    {
        $ruleAuthors = $assetFile->getLicence()->getInternalRuleAuthors();
        if (App::ZERO === $ruleAuthors->count()) {
            return true;
        }

        $assetAuthors = $assetFile->getAsset()->getAuthors();
        if ($assetAuthors->isEmpty()) {
            return false;
        }

        foreach ($assetAuthors as $author) {
            if (false === $ruleAuthors->exists(fn (int $key, Author $ruleAuthor): bool => $ruleAuthor->getId() === $author->getId())) {
                return false;
            }
        }

        return true;
    }

    private function validateUsers(AssetFile $assetFile): bool
    {
        $ruleUsers = $assetFile->getLicence()->getInternalRuleUsers();
        if (App::ZERO === $ruleUsers->count()) {
            return true;
        }

        /** @var DamUser $createdBy */
        $createdBy = $assetFile->getCreatedBy();

        return $ruleUsers->exists(fn (int $key, DamUser $ruleUser): bool => $ruleUser->getId() === $createdBy->getId());
    }
}
