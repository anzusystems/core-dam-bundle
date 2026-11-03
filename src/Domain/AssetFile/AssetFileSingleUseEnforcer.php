<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use DateTimeImmutable;

/**
 * The one place deciding which asset files must be single use; today the rule is the licence
 * flag, further rules (e.g. by file name) belong here as well.
 */
final readonly class AssetFileSingleUseEnforcer
{
    public function __construct(
        private DamLogger $damLogger,
        private bool $singleUseEnforced,
    ) {
    }

    public function enforce(AssetFile $assetFile): bool
    {
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse($assetFile)) {
            return false;
        }
        if (false === $this->allowSwitchToSingleUse($assetFile)) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);
        $this->armUsageCheck($assetFile);

        return true;
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function switchSingleUse(AssetFile $assetFile, bool $singleUse): void
    {
        $becomesSingleUse = $singleUse && false === $assetFile->getFlags()->isSingleUse();
        if ($becomesSingleUse && false === $this->allowSwitchToSingleUse($assetFile)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_SINGLE_USE_AFTER_FIRST_USE);
        }

        $assetFile->getFlags()->setSingleUse($singleUse);
        if ($becomesSingleUse) {
            $this->armUsageCheck($assetFile);
        }
    }

    /**
     * Every path that turns a file single use ends here, not just the licence rule above: nobody holds it in
     * the register yet, and only the ext system knows who really uses it. The reconcile discovers that, but
     * only for files it can see ({@see \AnzuSystems\CoreDamBundle\Repository\AssetFileRepository::findUsageChecksDue}),
     * so a file that becomes single use without a check date stays outside its reach for good.
     */
    private function armUsageCheck(AssetFile $assetFile): void
    {
        $assetFile->getAssetAttributes()->setUsedByCheckAfter(AssetFileManager::nextUsageCheck());
    }

    /**
     * A file somebody has already used cannot become single use: DAM does not know how many holders point
     * at it, so exclusivity could not be honoured for it. Until every host sends a holder, the switch is
     * only logged and still allowed (#85974).
     */
    private function allowSwitchToSingleUse(AssetFile $assetFile): bool
    {
        if (false === $assetFile->getFirstUsedAt() instanceof DateTimeImmutable) {
            return true;
        }
        if ($this->singleUseEnforced) {
            return false;
        }

        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_PROCESS,
            sprintf('Asset file %s switched to single use after its first use', (string) $assetFile->getId()),
        );

        return true;
    }

    private function mustBeSingleUse(AssetFile $assetFile): bool
    {
        return $assetFile->getLicence()->getFlags()->isSingleUseEnforced();
    }
}
