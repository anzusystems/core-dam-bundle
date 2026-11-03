<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileGroupUsage;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;

final readonly class AssetFileGroupUsageReader
{
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    /**
     * Used beats unanswered beats unused: one file the ext system still points at holds the whole group,
     * and one it did not answer for is enough to leave the group alone. Holders are collected across the
     * group and deduplicated — every file of a take-over group is the same photo. A group whose answer
     * lists no holder has an empty list, only a group no answer reports holders for has none (null).
     *
     * @param list<AssetFile> $files
     *
     * @return array<string, AssetFileGroupUsage> keyed by take-over root id
     */
    public function readByRoot(array $files): array
    {
        $rootIds = array_values(array_unique(array_map(
            static fn (AssetFile $assetFile): string => $assetFile->getTakeOverRootId(),
            $files,
        )));

        $group = $this->assetFileRepository->findGroupFiles($rootIds);
        $imageFiles = array_filter($group, static fn (AssetFile $assetFile): bool => $assetFile instanceof ImageFile);
        $answer = $this->extSystemCallbackFacade->resolveImageFileUsage($imageFiles);

        $usedByRoot = array_fill_keys($rootIds, false);
        $holdersByRoot = array_fill_keys($rootIds, null);
        foreach ($imageFiles as $imageFile) {
            $rootId = $imageFile->getTakeOverRootId();
            $usage = $answer[(string) $imageFile->getId()] ?? null;

            if (null === $usage) {
                $usedByRoot[$rootId] = true === $usedByRoot[$rootId] ? true : null;

                continue;
            }
            if ($usage->isUsed() && true !== $usedByRoot[$rootId]) {
                $usedByRoot[$rootId] = true;
            }
            $holders = $usage->getHolders();
            if (null === $holders) {
                continue;
            }

            $holdersByRoot[$rootId] ??= [];
            foreach ($holders as $holder) {
                $holdersByRoot[$rootId][$holder->getKey()] = $holder;
            }
        }

        $usageByRoot = [];
        foreach ($rootIds as $rootId) {
            $holders = $holdersByRoot[$rootId];
            $usageByRoot[$rootId] = new AssetFileGroupUsage(
                $usedByRoot[$rootId],
                null === $holders ? null : array_values($holders),
            );
        }

        return $usageByRoot;
    }
}
