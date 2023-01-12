<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertyAccessor;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use Doctrine\ORM\NonUniqueResultException;

final class DistributionBodyBuilder extends DistributionManager
{
    public function __construct(
        private readonly CurrentAnzuUserProvider $userProvider,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly AssetPropertyAccessor $accessor,
        private readonly AssetTextsWriter $textsWriter,
    ) {
    }

    public function setBaseFields(string $distributionService, Distribution $targetDistribution): void
    {
        $targetDistribution->setId('');
        $targetDistribution->setCreatedBy($this->userProvider->getCurrentUser());
        $targetDistribution->setModifiedBy($this->userProvider->getCurrentUser());
        $targetDistribution->setDistributionService($distributionService);
    }

    public function getKeywords(AssetFile $assetFile): array
    {
        return $assetFile->getAsset()->getKeywords()->map(
            fn (Keyword $keyword): string => $keyword->getName()
        )->getValues();
    }

    public function getFirstAuthor(AssetFile $assetFile): string
    {
        $author = $assetFile->getAsset()->getAuthors()->first();

        if ($author instanceof Author) {
            return $author->getName();
        }

        return '';
    }

    /**
     * @throws NonUniqueResultException
     */
    public function setWriterProperties(string $distributionService, Asset $assetFile, object $object): void
    {
        $requirements = $this->extSystemConfigurationProvider->getDistributionRequirements(
            $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($assetFile),
            $distributionService
        );

        // todo
        $this->textsWriter->writeValues($assetFile, $object, $requirements->getMetadataMap());
    }

    /**
     * @throws NonUniqueResultException
     */
    public function setProperties(string $distributionService, AssetFile $assetFile, object $object): void
    {
        $requirements = $this->extSystemConfigurationProvider->getDistributionRequirements(
            $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetFile($assetFile),
            $distributionService
        );

        foreach ($requirements->getMetadataMap() as $property => $configs) {
            $value = $this->accessor->getPropertyValue(
                $assetFile->getAsset(),
                $configs
            );
            $setter = 'set' . ucfirst($property);
            if (method_exists($object, $setter)) {
                $object->{$setter}($value);
            }
        }
    }
}
