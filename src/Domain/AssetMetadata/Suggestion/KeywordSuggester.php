<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordFacade;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

final class KeywordSuggester extends AbstractSuggester
{
    public function __construct(
        private readonly KeywordRepository $keywordRepository,
        private readonly KeywordFactory $keywordFactory,
        private readonly KeywordFacade $keywordFacade,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ORMException
     */
    protected function storeSuggestionsOnAsset(Asset $asset, array $suggestions): void
    {
        $asset->getMetadata()->setKeywordSuggestions($suggestions);
        foreach ($suggestions as $ids) {
            // No duplicate suggestions, add it to the asset entity.
            if (1 === count($ids)) {
                /** @var Keyword $keyword */
                $keyword = $this->entityManager->getReference(Keyword::class, $ids[0]);
                $asset->getKeywords()->add($keyword);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    protected function suggestIdsByTag(string $name, Asset $asset): array
    {
        $extSystem = $asset->getExtSystem();
        $ids = $this->keywordRepository->findIdsByNameAndExtSystem(
            name: $name,
            extSystem: $extSystem
        );
        // 1. Some entity exists
        if ($ids) {
            return $ids;
        }

        // 2. Entity doesn't exist, create it.
        $keyword = $this->keywordFactory->create($name, $extSystem);
        $asset->getKeywords()->add($keyword);
        $this->keywordFacade->create($keyword);

        return $ids;
    }

    protected function getConfiguration(AssetFile $assetFile): ExtSystemAssetTypeExifMetadataConfiguration
    {
        return $this->configurationCache ??= $this->extSystemConfigurationProvider
            ->getExtSystemConfigurationByAssetFile($assetFile)
            ->getKeywords();
    }
}
