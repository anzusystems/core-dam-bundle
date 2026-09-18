<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use DateTimeImmutable;

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
    public function recordFromRequest(ImageFirstUseRequestDto $dto): void
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
        $this->damLogger->warnUnknownDamIds(
            DamLogger::NAMESPACE_ASSET_FILE_FIRST_USE,
            'First-use batch',
            $damIds,
            $assetFilesByDamId,
        );
        $assetFilesByDamId = $this->filterAuthorized($assetFilesByDamId);
        $roots = $this->findTakeOverRoots($assetFilesByDamId);

        foreach ($dto->getItems() as $item) {
            $assetFile = $assetFilesByDamId[$item->getDamId()] ?? null;
            if (false === $assetFile instanceof AssetFile) {
                continue;
            }

            $this->stampFirstUse($assetFile, $roots, $item->getFirstUsedAt());
        }

        $this->assetFileManager->flush();
    }

    /**
     * Deliberately not routed through {@see filterAuthorized()}: the caller is the server itself recording
     * the use it has just granted, not an external client.
     *
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
            $this->stampFirstUse($assetFile, $roots, $firstUsedAt);
        }

        $this->assetFileManager->flush();
    }

    /**
     * @param array<string, AssetFile> $roots
     */
    private function stampFirstUse(AssetFile $assetFile, array $roots, ?DateTimeImmutable $firstUsedAt): void
    {
        // Write-once: the first recorded use date is never overwritten.
        if (null === $assetFile->getFirstUsedAt()) {
            $assetFile->setFirstUsedAt($firstUsedAt);
            $this->assetFileManager->updateExisting($assetFile, flush: false);
        }
        $this->stampTakeOverRoot($assetFile, $roots, $firstUsedAt);
    }

    /**
     * A take-over is the same photo as the file it came from, so the licence clock starts with the first use
     * of any of them — the child's own recorded date wins, the incoming one is used only when the child has
     * none. Deliberately not re-checked against {@see filterAuthorized()}: the root is reachable only through
     * a file the caller was already authorized for. A root absent from the loaded set may be gone (retention
     * deletes agency originals while their take-overs live on), which is an expected state, not an error.
     *
     * @param array<string, AssetFile> $roots
     */
    private function stampTakeOverRoot(AssetFile $assetFile, array $roots, ?DateTimeImmutable $itemFirstUsedAt): void
    {
        $rootId = $assetFile->getAssetAttributes()
            ->getTakenOverFromId();
        $root = $roots[$rootId] ?? null;
        if (false === $root instanceof AssetFile || null !== $root->getFirstUsedAt()) {
            return;
        }

        $root->setFirstUsedAt($assetFile->getFirstUsedAt() ?? $itemFirstUsedAt);
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
            $rootId = $assetFile->getAssetAttributes()
                ->getTakenOverFromId();
            if (App::EMPTY_STRING === $rootId) {
                continue;
            }

            $rootIds[$rootId] = true;
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
