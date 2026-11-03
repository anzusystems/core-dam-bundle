<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use DateTimeImmutable;

final readonly class AssetFileFirstUseFacade
{
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileManager $assetFileManager,
    ) {
    }

    /**
     * @param list<AssetFile> $assetFiles
     */
    public function record(array $assetFiles, DateTimeImmutable $firstUsedAt): void
    {
        $assetFilesByDamId = [];
        foreach ($assetFiles as $assetFile) {
            $assetFilesByDamId[$assetFile->getId()] = $assetFile;
        }
        $roots = $this->findTakeOverRoots($assetFilesByDamId);

        foreach ($assetFilesByDamId as $assetFile) {
            $this->stampFirstUse(assetFile: $assetFile, roots: $roots, firstUsedAt: $firstUsedAt);
        }

        $this->assetFileManager->flush();
    }

    /**
     * @param array<string, AssetFile> $roots
     */
    private function stampFirstUse(AssetFile $assetFile, array $roots, DateTimeImmutable $firstUsedAt): void
    {
        // Write-once: the first recorded use date is never overwritten.
        if (null === $assetFile->getFirstUsedAt()) {
            $assetFile->setFirstUsedAt($firstUsedAt);
            $this->assetFileManager->updateExisting($assetFile, flush: false);
        }
        $this->stampTakeOverRoot(assetFile: $assetFile, roots: $roots);
    }

    /**
     * A take-over is the same photo as the file it came from, so the original counts as used from the first use
     * of any of them, so it takes the child's recorded date. A root absent from the loaded set may be gone
     * (retention deletes agency originals while their take-overs live on), which is an expected state, not
     * an error.
     *
     * @param array<string, AssetFile> $roots
     */
    private function stampTakeOverRoot(AssetFile $assetFile, array $roots): void
    {
        $rootId = $assetFile->getAssetAttributes()
            ->getTakenOverFromId();
        $root = $roots[$rootId] ?? null;
        if (false === $root instanceof AssetFile || null !== $root->getFirstUsedAt()) {
            return;
        }

        $root->setFirstUsedAt($assetFile->getFirstUsedAt());
        $this->assetFileManager->updateExisting($root, flush: false);
    }

    /**
     * @param array<string, AssetFile> $assetFilesByDamId
     *
     * @return array<string, AssetFile>
     */
    private function findTakeOverRoots(array $assetFilesByDamId): array
    {
        $rootIds = [];
        foreach ($assetFilesByDamId as $assetFile) {
            $attributes = $assetFile->getAssetAttributes();
            if (false === $attributes->isTakenOver()) {
                continue;
            }

            $rootIds[$attributes->getTakenOverFromId()] = true;
        }

        if ([] === $rootIds) {
            return [];
        }

        $roots = [];
        foreach ($this->assetFileRepository->findByIds(array_keys($rootIds)) as $root) {
            $roots[$root->getId()] = $root;
        }

        return $roots;
    }

}
