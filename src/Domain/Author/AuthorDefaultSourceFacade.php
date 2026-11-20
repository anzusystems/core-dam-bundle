<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use Generator;

/**
 * Links the default source straight in SQL and leaves the search index to `anzu-dam:elastic:rebuild`: reindexing
 * millions of images through the entities is 7 times slower than the DBAL rebuild.
 */
final class AuthorDefaultSourceFacade
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly AssetSourceDBALRepository $assetSourceDBALRepository,
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
                $lastId = $assetIds[array_key_last($assetIds)];
            }

            yield count($assetIds);
        } while ($this->batchSize === count($assetIds));
    }
}
