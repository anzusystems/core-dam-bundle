<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

final class AuthorSuggester extends AbstractSuggester
{
    public function __construct(
        private readonly AuthorRepository $authorRepo,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorFacade $authorFacade,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ORMException
     */
    protected function storeSuggestionsOnAsset(Asset $asset, array $suggestions): void
    {
        $asset->getMetadata()->setAuthorSuggestions($suggestions);
        foreach ($suggestions as $ids) {
            // No duplicate suggestions, add it to the asset entity.
            if (1 === count($ids) && false === $asset->getAuthors()->containsKey($ids[0])) {
                /** @var Author $author */
                $author = $this->entityManager->getReference(Author::class, $ids[0]);
                $asset->getAuthors()->add($author);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    protected function suggestIdsByTag(string $name, Asset $asset): array
    {
        $extSystem = $asset->getExtSystem();
        $ids = $this->authorRepo->findIdsByNameAndExtSystem(
            name: $name,
            extSystem: $extSystem
        );
        // 1. Some entity exists
        if ($ids) {
            return $ids;
        }

        // 2. Entity doesn't exist, create it.
        $author = $this->authorFactory->create($name, $extSystem);
        $this->authorFacade->create($author);

        return $ids;
    }

    protected function getConfiguration(AssetFile $assetFile): ExtSystemAssetTypeExifMetadataConfiguration
    {
        return $this->configurationCache ??= $this->extSystemConfigurationProvider
            ->getExtSystemConfigurationByAssetFile($assetFile)
            ->getAuthors();
    }
}
