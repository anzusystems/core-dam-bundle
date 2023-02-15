<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetSlot;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetSlot\AssetSlotAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetSlot\AssetSlotMinimalAdmDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;

class AssetSlotFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AssetSlotManager $assetSlotManager,
        private readonly AssetSlotFactory $assetSlotFactory,
        private readonly AssetManager $assetManager,
    ) {
    }

    public function decorateAssetSlots(Asset $asset): ApiResponseList
    {
        return (new ApiResponseList())
            ->setData(
                $asset->getSlots()->map(
                    fn (AssetSlot $assetSlot): AssetSlotAdmListDto => AssetSlotAdmListDto::getInstance($assetSlot)
                )->toArray()
            )
            ->setTotalCount(
                $asset->getSlots()->count()
            );
    }

    /**
     * @param Collection<int, AssetSlotMinimalAdmDto> $list
     *
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function update(Asset $asset, Collection $list): ApiResponseList
    {
        $this->validator->validate($list);
        $this->validateOwnership($asset, $list);

        /** @var ArrayCollection<int, AssetSlot> $newSlots */
        $newSlots = new ArrayCollection();
        foreach ($list as $minimalSlot) {
            $slot = $asset->getSlots()->filter(fn (AssetSlot $slot): bool => $slot->getName() === $minimalSlot->getSlotName())->first();
            if ($slot instanceof AssetSlot) {
                $newSlots->add($slot->setAssetFile($minimalSlot->getAssetFile()));

                continue;
            }

            $newSlots->add(
                $this->assetSlotFactory->createRelation(
                    asset: $asset,
                    assetFile: $minimalSlot->getAssetFile(),
                    slotName: $minimalSlot->getSlotName(),
                    flush: false
                )
            );
        }

        CollectionHelper::colDiff($asset->getSlots(), $newSlots)->map(
            fn (AssetSlot $slot): bool => $this->assetSlotManager->delete($slot, false)
        );

        return $this->decorateAssetSlots($this->assetManager->updateExisting($asset));
    }

    /**
     * @param Collection<int, AssetSlotMinimalAdmDto> $list
     *
     * @throws ValidationException
     * @throws ForbiddenOperationException
     */
    private function validateOwnership(Asset $asset, Collection $list): void
    {
        foreach ($asset->getSlots() as $slot) {
            if (null === CollectionHelper::findFirst(
                $list,
                fn (AssetSlotMinimalAdmDto $minimalSlot): bool => false === ($minimalSlot->getAssetFile() === $slot->getAssetFile())
            )
            ) {
                throw new ForbiddenOperationException(ForbiddenOperationException::ASSET_DELETE_DURING_REORDER);
            }
        }

        foreach ($list as $index => $minimalSlot) {
            if (false === ($minimalSlot->getAssetFile()->getAsset() === $asset)) {
                throw (new ValidationException())
                    ->addFormattedError("[{$index}].assetFile", ValidationException::ERROR_FIELD_INVALID);
            }
        }
    }
}
