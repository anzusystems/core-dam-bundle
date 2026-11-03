<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileSingleUseInventoryResult;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileSingleUseInventoryRow;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Closure;

/**
 * Read-only: lists the single use groups the ext system reports with more than one holder.
 */
final readonly class AssetFileSingleUseInventoryFacade
{
    private const int PAGE_SIZE = 100;
    private const int MIN_HOLDERS = 2;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private ImageFileRepository $imageFileRepository,
        private AssetFileRepository $assetFileRepository,
        private AssetFileGroupUsageReader $assetFileGroupUsageReader,
        private AssetFileManager $assetFileManager,
    ) {
    }

    /**
     * @param Closure(AssetFileSingleUseInventoryRow): void $onGroup called once per group with 2+ holders
     */
    public function inventory(Closure $onGroup): AssetFileSingleUseInventoryResult
    {
        $seenRoots = [];
        $checked = App::ZERO;
        $withManyHolders = App::ZERO;
        $unanswered = App::ZERO;
        $afterId = App::EMPTY_STRING;

        do {
            $page = $this->imageFileRepository->findSingleUseAfter($afterId, self::PAGE_SIZE);
            if ([] === $page) {
                break;
            }
            $afterId = (string) $page[array_key_last($page)]->getId();

            $pending = array_values(array_filter(
                $page,
                static fn (ImageFile $imageFile): bool => false === isset($seenRoots[$imageFile->getTakeOverRootId()]),
            ));
            foreach ($pending as $imageFile) {
                $seenRoots[$imageFile->getTakeOverRootId()] = true;
            }

            $usageByRoot = $this->assetFileGroupUsageReader->readByRoot($pending);
            $roots = [];
            foreach ($this->assetFileRepository->findByIds(array_keys($usageByRoot)) as $root) {
                $roots[(string) $root->getId()] = $root;
            }
            foreach ($usageByRoot as $rootId => $usage) {
                ++$checked;
                $holders = $usage->getHolders();
                if (null === $holders) {
                    ++$unanswered;

                    continue;
                }
                if (self::MIN_HOLDERS > count($holders)) {
                    continue;
                }

                ++$withManyHolders;
                $root = $roots[$rootId] ?? null;
                $onGroup($this->createRow(
                    rootId: $rootId,
                    root: $root instanceof ImageFile ? $root : null,
                    holders: $holders,
                ));
            }

            $this->assetFileManager->clear();
        } while (self::PAGE_SIZE === count($page));

        return new AssetFileSingleUseInventoryResult(
            checked: $checked,
            withManyHolders: $withManyHolders,
            unanswered: $unanswered,
        );
    }

    /**
     * @param list<ImageHolderDto> $holders
     */
    private function createRow(string $rootId, ?ImageFile $root, array $holders): AssetFileSingleUseInventoryRow
    {
        $attributes = $root?->getAssetAttributes();

        return new AssetFileSingleUseInventoryRow(
            rootId: $rootId,
            assetId: null === $root ? App::EMPTY_STRING : (string) $root->getAsset()->getId(),
            licenceId: null === $root ? App::EMPTY_STRING : (string) $root->getLicence()->getId(),
            recordedHolder: null === $attributes
                ? App::EMPTY_STRING
                : ImageHolderDto::keyOf($attributes->getUsedByHolderName(), $attributes->getUsedByHolderId()),
            holders: array_map(
                static fn (ImageHolderDto $holder): string => $holder->getKey(),
                $holders,
            ),
            firstUsedAt: $root?->getFirstUsedAt(),
        );
    }
}
