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
        $roots = $this->findTakeOverRoots($assetFiles);
        foreach ($assetFiles as $assetFile) {
            if (null === $assetFile->getFirstUsedAt()) {
                $assetFile->setFirstUsedAt($firstUsedAt);
                $this->assetFileManager->updateExisting($assetFile, flush: false);
            }

            $root = $roots[$assetFile->getAssetAttributes()->getTakenOverFromId()] ?? null;
            if ($root instanceof AssetFile && null === $root->getFirstUsedAt()) {
                $root->setFirstUsedAt($assetFile->getFirstUsedAt());
                $this->assetFileManager->updateExisting($root, flush: false);
            }
        }

        $this->assetFileManager->flush();
    }

    /**
     * A root absent from the result may be gone: retention deletes agency originals while their take-overs
     * live on.
     *
     * @param list<AssetFile> $assetFiles
     *
     * @return array<string, AssetFile>
     */
    private function findTakeOverRoots(array $assetFiles): array
    {
        $rootIds = [];
        foreach ($assetFiles as $assetFile) {
            $attributes = $assetFile->getAssetAttributes();
            if ($attributes->isTakenOver()) {
                $rootIds[$attributes->getTakenOverFromId()] = true;
            }
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
