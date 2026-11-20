<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetFileDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use Generator;

/**
 * Backfill for files created before a single use rule applied to them. It writes straight in SQL and leaves the
 * search index to `anzu-dam:elastic:rebuild` and the ext system to a JobSynchronizeImageChanged: through the entities
 * it took about four queries, a callback and a reindex per asset.
 */
final readonly class AssetFileSingleUseFacade
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private AssetFileDBALRepository $assetFileDBALRepository,
        private AssetSourceDBALRepository $assetSourceDBALRepository,
        private AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer,
    ) {
    }

    /**
     * Yields the files checked in a batch as the key and the files switched (to switch on a dry run) as the value.
     *
     * @return Generator<int, int>
     */
    public function enforceForLicence(AssetLicence $licence, bool $dryRun): Generator
    {
        $lastId = App::EMPTY_STRING;
        do {
            $files = $this->assetFileDBALRepository->findNotSingleUseByLicence((int) $licence->getId(), $lastId, self::BATCH_SIZE);
            $ids = array_column(array_filter($files, $this->mustBeSingleUse(...)), 'id');
            $switched = $dryRun || empty($ids) ? count($ids) : $this->assetFileDBALRepository->setSingleUse($ids);
            if (false === empty($files)) {
                $lastId = $files[array_key_last($files)]['id'];
            }

            yield count($files) => $switched;
        } while (self::BATCH_SIZE === count($files));
    }

    /**
     * Yields the assets checked in a batch as the key and the files switched (to switch on a dry run) as the value.
     *
     * @return Generator<int, int>
     */
    public function enforceForSource(Author $source, bool $dryRun): Generator
    {
        $lastId = App::EMPTY_STRING;
        do {
            $assetIds = $this->assetSourceDBALRepository->findAssetIdsBySource((string) $source->getId(), $lastId, self::BATCH_SIZE);
            $switched = App::ZERO;
            if (false === empty($assetIds)) {
                $switched = $dryRun
                    ? $this->assetFileDBALRepository->countNotSingleUseByAssetIds($assetIds)
                    : $this->assetFileDBALRepository->setSingleUseByAssetIds($assetIds);
                $lastId = $assetIds[array_key_last($assetIds)];
            }

            yield count($assetIds) => $switched;
        } while (self::BATCH_SIZE === count($assetIds));
    }

    /**
     * @param array{id: string, licence_enforced: int|string, source_enforced: int|string, exif_data: string|null} $file
     */
    private function mustBeSingleUse(array $file): bool
    {
        return $this->assetFileSingleUseEnforcer->mustBeSingleUse(
            (bool) $file['licence_enforced'],
            (bool) $file['source_enforced'],
            (array) json_decode($file['exif_data'] ?? '[]', true),
        );
    }
}
