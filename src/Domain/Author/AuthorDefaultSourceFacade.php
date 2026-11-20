<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

/**
 * Links the default source straight in SQL, a batch of images at a time: millions of images need no logic of the
 * entities.
 */
final class AuthorDefaultSourceFacade
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly AssetSourceDBALRepository $assetSourceDBALRepository,
        private readonly AssetRepository $assetRepository,
        private readonly IndexManager $indexManager,
        private readonly EntityManagerInterface $entityManager,
        private int $batchSize = self::BATCH_SIZE,
    ) {
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Yields the number of images of each batch.
     *
     * @return Generator<int, int>
     */
    public function fillDefaultSource(ExtSystem $extSystem, Author $source): Generator
    {
        $lastId = App::EMPTY_STRING;
        do {
            $assetIds = $this->assetSourceDBALRepository->findImageIdsWithFileWithoutSource(
                (int) $extSystem->getId(),
                $lastId,
                $this->batchSize,
            );
            if (false === empty($assetIds)) {
                $this->assetSourceDBALRepository->linkSource($assetIds, (string) $source->getId());
                $this->indexManager->indexBulk($this->assetRepository->findByIds($assetIds)->getValues());
                $this->entityManager->clear();
                $lastId = $assetIds[array_key_last($assetIds)];
            }

            yield count($assetIds);
        } while ($this->batchSize === count($assetIds));
    }
}
