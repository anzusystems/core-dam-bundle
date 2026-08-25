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
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

final readonly class AssetFileFirstUseFacade
{
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileManager $assetFileManager,
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
        $this->logUnknownDamIds($damIds, $assetFilesByDamId);
        $assetFilesByDamId = $this->filterAuthorized($assetFilesByDamId);

        foreach ($dto->getItems() as $item) {
            $assetFile = $assetFilesByDamId[$item->getDamId()] ?? null;
            // Write-once: the first recorded use date is never overwritten.
            if ($assetFile instanceof AssetFile && null === $assetFile->getFirstUsedAt()) {
                $assetFile->setFirstUsedAt($item->getFirstUsedAt());
                $this->assetFileManager->updateExisting($assetFile, flush: false);
            }
        }

        $this->assetFileManager->flush();
    }

    /**
     * @param string[] $damIds
     * @param array<string, AssetFile> $assetFilesByDamId
     */
    private function logUnknownDamIds(array $damIds, array $assetFilesByDamId): void
    {
        // Unknown ids signal CMS<->DAM drift, so they surface in logs even though the batch succeeds.
        $unknownDamIds = array_diff($damIds, array_keys($assetFilesByDamId));
        if ([] === $unknownDamIds) {
            return;
        }

        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_FIRST_USE,
            sprintf('First-use batch skipped %d unknown damId(s) (%s)', count($unknownDamIds), implode(',', $unknownDamIds)),
        );
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
