<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageSyncDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageSyncResultDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Owns the answer to "who uses this photo". The caller does not claim and release, it states the full
 * content of one usage scope; releases are computed here, so a usage the caller silently dropped cannot
 * keep an exclusivity claim alive.
 *
 * Everything is decided per take-over group ({@see AssetFile::getTakeOverRootId()}): an agency original
 * and its licence copies are one photo, so holding any of them holds all of them.
 */
final class ImageUsageSyncFacade
{
    use ValidatorAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetFileManager $assetFileManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function sync(ImageUsageSyncDto $dto): ImageUsageSyncResultDto
    {
        $this->validator->validate($dto);

        try {
            $this->entityManager->beginTransaction();
            $result = $this->syncScope($dto);
            $this->assetFileManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        return $result;
    }

    private function syncScope(ImageUsageSyncDto $dto): ImageUsageSyncResultDto
    {
        $damIds = array_values(array_unique($dto->getDamIds()->toArray()));
        $rootIdByDamId = $this->resolveRoots($damIds);
        $groups = $this->loadGroups($rootIdByDamId);

        $conflicts = [];
        foreach ($rootIdByDamId as $damId => $rootId) {
            $group = $groups[$rootId] ?? [];
            $holder = $this->findForeignHolder($group, $dto);
            if ($holder instanceof AssetFileAttributes) {
                $conflicts[] = ImageUsageConflictDto::getInstance((string) $damId, $holder);

                continue;
            }

            foreach ($group as $assetFile) {
                $this->claim($assetFile, $dto);
            }
        }

        $this->releaseDroppedFiles($dto, $rootIdByDamId);

        return ImageUsageSyncResultDto::getInstance($conflicts);
    }

    /**
     * Files the scope still holds although the request no longer lists their group. A requested group is
     * never released here, not even a conflicting one — a conflict leaves its group entirely untouched.
     *
     * @param array<string, string> $rootIdByDamId
     */
    private function releaseDroppedFiles(ImageUsageSyncDto $dto, array $rootIdByDamId): void
    {
        $requestedRootIds = array_flip($rootIdByDamId);

        $held = $this->assetFileRepository->findByUsedByScope(
            $dto->getScopeResourceName(),
            $dto->getScopeResourceId(),
            lock: true,
        );

        foreach ($held as $assetFile) {
            if (isset($requestedRootIds[$assetFile->getTakeOverRootId()])) {
                continue;
            }

            $this->release($assetFile);
        }
    }

    /**
     * @param string[] $damIds
     *
     * @return array<string, string> damId => take-over root id
     */
    private function resolveRoots(array $damIds): array
    {
        if ([] === $damIds) {
            return [];
        }

        $rootIdByDamId = [];
        foreach ($this->assetFileRepository->findByIds($damIds) as $assetFile) {
            $rootIdByDamId[(string) $assetFile->getId()] = $assetFile->getTakeOverRootId();
        }
        $this->logUnknownDamIds($damIds, $rootIdByDamId);

        return $rootIdByDamId;
    }

    /**
     * Locked for update: the whole decision is read-then-write, so without holding the rows two requests
     * asking for the same free photo would both read it as free and both claim it.
     *
     * @param array<string, string> $rootIdByDamId
     *
     * @return array<string, list<AssetFile>> take-over root id => files of that group
     */
    private function loadGroups(array $rootIdByDamId): array
    {
        $groups = [];
        $files = $this->assetFileRepository->findGroupFiles(array_values(array_unique($rootIdByDamId)), lock: true);

        foreach ($files as $assetFile) {
            $groups[$assetFile->getTakeOverRootId()][] = $assetFile;
        }

        return $groups;
    }

    /**
     * @param list<AssetFile> $group
     */
    private function findForeignHolder(array $group, ImageUsageSyncDto $dto): ?AssetFileAttributes
    {
        foreach ($group as $assetFile) {
            $attributes = $assetFile->getAssetAttributes();
            if (App::EMPTY_STRING === $attributes->getUsedByScopeName()) {
                continue;
            }
            if (
                $attributes->getUsedByScopeName() === $dto->getScopeResourceName()
                && $attributes->getUsedByScopeId() === $dto->getScopeResourceId()
            ) {
                continue;
            }

            return $attributes;
        }

        return null;
    }

    private function claim(AssetFile $assetFile, ImageUsageSyncDto $dto): void
    {
        $this->write(
            $assetFile,
            $dto->getScopeResourceName(),
            $dto->getScopeResourceId(),
            $dto->getHolderResourceName(),
            $dto->getHolderResourceId(),
        );
    }

    private function release(AssetFile $assetFile): void
    {
        $this->write($assetFile, App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING);
    }

    private function write(
        AssetFile $assetFile,
        string $scopeName,
        string $scopeId,
        string $holderName,
        string $holderId,
    ): void {
        $attributes = $assetFile->getAssetAttributes();
        if (
            $attributes->getUsedByScopeName() === $scopeName
            && $attributes->getUsedByScopeId() === $scopeId
            && $attributes->getUsedByResourceName() === $holderName
            && $attributes->getUsedByResourceId() === $holderId
        ) {
            return;
        }

        $attributes
            ->setUsedByScopeName($scopeName)
            ->setUsedByScopeId($scopeId)
            ->setUsedByResourceName($holderName)
            ->setUsedByResourceId($holderId)
        ;
        // Deliberately not through AssetFileManager::updateExisting(): it runs the single use enforcer, which
        // lazy loads the licence of every touched file, and this write changes no licence and no flag. The
        // entity is managed, so the single flush in sync() persists it.
    }

    /**
     * @param string[] $damIds
     * @param array<string, string> $rootIdByDamId
     */
    private function logUnknownDamIds(array $damIds, array $rootIdByDamId): void
    {
        // Unknown ids signal CMS<->DAM drift, so they surface in logs even though the sync succeeds.
        $unknownDamIds = array_diff($damIds, array_keys($rootIdByDamId));
        if ([] === $unknownDamIds) {
            return;
        }

        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_USAGE,
            sprintf('Usage sync skipped %d unknown damId(s) (%s)', count($unknownDamIds), implode(',', $unknownDamIds)),
        );
    }
}
