<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Throwable;

/**
 * Hands single use photos the caller already uses from one of its holders to another — a gallery embedded
 * into an article, a suggestion made into a post. Not a pick: no licence rule, no take over and no first use
 * apply, so nothing here is refused. A group held by somebody else than the sender is left alone and reported,
 * whatever the enforcement switch says; a free group goes to the receiver.
 */
final class ImageTransferFacade
{
    use ValidatorAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileRepository $assetFileRepository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function transferImages(ImageTransferRequestDto $dto): ImageTransferResultDto
    {
        $this->validator->validate($dto);

        $requestedByRoot = [];
        foreach ($dto->getImageFileIds() as $imageFile) {
            if ($imageFile->getFlags()->isSingleUse()) {
                $requestedByRoot[$imageFile->getTakeOverRootId()][] = (string) $imageFile->getId();
            }
        }

        $result = new ImageTransferResultDto();
        if ([] === $requestedByRoot) {
            return $result;
        }
        $rootIds = array_keys($requestedByRoot);
        // The same ascending order ImageUseFacade and ImageReleaseFacade lock in: a deadlock defense.
        sort($rootIds);

        $from = UsageClaim::fromHolder($dto->getFrom());
        $to = UsageClaim::fromHolder($dto->getTo());

        try {
            $this->assetFileManager->beginTransaction();
            $groupByRoot = [];
            foreach ($this->assetFileRepository->findGroupFiles($rootIds, lock: true) as $file) {
                $groupByRoot[$file->getTakeOverRootId()][] = $file;
            }

            foreach ($groupByRoot as $rootId => $group) {
                $recorded = self::recordedHolder($group);
                if (null !== $recorded && false === $from->matches($recorded) && false === $to->matches($recorded)) {
                    foreach ($requestedByRoot[$rootId] ?? [] as $damId) {
                        $result->addNotHeldByFrom(ImageUsageConflictDto::getInstance($damId, $recorded));
                    }

                    continue;
                }

                foreach ($group as $file) {
                    $this->assetFileManager->updateUsage($file, $to, flush: false);
                }
                foreach ($requestedByRoot[$rootId] ?? [] as $damId) {
                    $result->addTransferred($damId);
                }
            }

            $this->assetFileManager->flush();
            $this->assetFileManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetFileManager->isTransactionActive()) {
                $this->assetFileManager->rollback();
            }

            throw $exception;
        }

        return $result;
    }

    /**
     * @param list<AssetFile> $group
     */
    private static function recordedHolder(array $group): ?AssetFileAttributes
    {
        foreach ($group as $file) {
            $attributes = $file->getAssetAttributes();
            if (App::EMPTY_STRING !== $attributes->getUsedByHolderName()) {
                return $attributes;
            }
        }

        return null;
    }
}
