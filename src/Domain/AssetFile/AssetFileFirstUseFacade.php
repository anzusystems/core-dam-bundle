<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetFileDBALRepository;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

final readonly class AssetFileFirstUseFacade
{
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileDBALRepository $assetFileDBALRepository,
        private AccessDenier $accessDenier,
        private Validator $validator,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * Partial-success semantics: unknown damIds and items in licences the caller is not
     * authorized for are skipped (skips are logged as warning), valid items are written.
     * A 4xx here would make the CMS drop the whole batch permanently.
     *
     * @throws ValidationException
     */
    public function processBatch(ImageFirstUseRequestDto $dto): void
    {
        $this->validator->validate($dto);

        $damIds = CollectionHelper::traversableToIds(
            $dto->getItems(),
            static fn (ImageFirstUseItemDto $item): string => $item->getDamId(),
        );

        $assetFilesByDamId = [];
        foreach ($this->assetFileRepository->findByIds($damIds) as $assetFile) {
            $assetFilesByDamId[$assetFile->getId()] = $assetFile;
        }
        $assetFilesByDamId = $this->filterAuthorized($assetFilesByDamId);

        $firstUsedAtByDamId = [];
        foreach ($dto->getItems() as $item) {
            if (isset($assetFilesByDamId[$item->getDamId()])) {
                $firstUsedAtByDamId[$item->getDamId()] = $item->getFirstUsedAt();
            }
        }

        $this->assetFileDBALRepository->updateFirstUsedAtIfUnset($firstUsedAtByDamId);
    }

    /**
     * @param array<string, AssetFile> $assetFilesByDamId
     *
     * @return array<string, AssetFile>
     */
    private function filterAuthorized(array $assetFilesByDamId): array
    {
        $grantedByLicenceId = [];
        $deniedLicenceIds = [];
        $authorized = [];

        foreach ($assetFilesByDamId as $damId => $assetFile) {
            $licence = $assetFile->getLicence();
            $licenceId = (int) $licence->getId();
            $grantedByLicenceId[$licenceId] ??= $this->accessDenier->isGranted(DamPermissions::DAM_IMAGE_UPDATE, $licence);

            if ($grantedByLicenceId[$licenceId]) {
                $authorized[$damId] = $assetFile;

                continue;
            }
            $deniedLicenceIds[$licenceId] = true;
        }

        if ([] !== $deniedLicenceIds) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_ASSET_FILE_FIRST_USE,
                sprintf(
                    'First-use batch skipped %d item(s) in unauthorized licences (%s)',
                    count($assetFilesByDamId) - count($authorized),
                    implode(',', array_keys($deniedLicenceIds)),
                ),
            );
        }

        return $authorized;
    }
}
