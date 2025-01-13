<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\Keyword;

final class DistributionBodyBuilder
{
    public function __construct(
        private readonly CurrentAnzuUserProvider $userProvider,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly AssetTextsWriter $textsWriter,
    ) {
    }

    public function setBaseFields(
        AssetFile $assetFile,
        string $distributionService,
        Distribution $targetDistribution
    ): void {
        $targetDistribution->setId('');
        $targetDistribution->setCreatedBy($this->userProvider->getCurrentUser());
        $targetDistribution->setModifiedBy($this->userProvider->getCurrentUser());
        $targetDistribution->setDistributionService($distributionService);
        $targetDistribution->setAssetFile($assetFile);
        $targetDistribution->setAsset($assetFile->getAsset());

        $targetDistribution->setAssetFileId((string) $assetFile->getId());
        $targetDistribution->setAssetId((string) $assetFile->getAsset()->getId());
        $targetDistribution->setExtSystem($assetFile->getExtSystem());
    }

    public function getKeywords(AssetFile $assetFile): array
    {
        return $assetFile->getAsset()->getKeywords()->map(
            fn (Keyword $keyword): string => $keyword->getName()
        )->getValues();
    }

    public function setWriterProperties(string $distributionService, Asset $assetFile, object $object): void
    {
        $requirements = $this->extSystemConfigurationProvider->getDistributionRequirements(
            $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($assetFile),
            $distributionService
        );

        $this->textsWriter->writeValues($assetFile, $object, $requirements->getMetadataMap());
    }
}
