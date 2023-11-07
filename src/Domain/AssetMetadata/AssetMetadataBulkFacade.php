<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AssetMetadataBulkFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly AssetManager $assetManager,
        private readonly AccessDenier $accessDenier,
    ) {
    }

    /**
     * @param Collection<int, FormProvidableMetadataBulkUpdateDto> $list
     *
     * @throws ValidationException
     * @throws AccessDeniedException
     * @throws NonUniqueResultException
     */
    public function bulkUpdate(Collection $list): Collection
    {
        $this->validateMaxBulkCount($list);
        $this->validator->validate($list);
        $updated = [];

        foreach ($list as $updateDto) {
            $this->checkPermissions($updateDto);
            $asset = $updateDto->getAsset();

            $this->assetMetadataManager->updateFromMetadataBulkDto($asset, $updateDto, false);
            $updated[] = FormProvidableMetadataBulkUpdateDto::getInstance(
                asset: $this->assetManager->updateFromMetadataBulkDto($asset, $updateDto, false)
            );
            if ($updateDto->isDescribed()) {
                $this->assetMetadataManager->removeSuggestions($updateDto->getAsset()->getMetadata(), false);
            }
        }

        $this->assetManager->flush();
        foreach ($list as $updateDto) {
            $this->indexManager->index($updateDto->getAsset());
        }

        return new ArrayCollection($updated);
    }

    /**
     * @param Collection<int, FormProvidableMetadataBulkUpdateDto> $dtoList
     */
    private function validateMaxBulkCount(Collection $dtoList): void
    {
        if ($dtoList->count() > $this->configurationProvider->getSettings()->getMaxBulkItemCount()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_BULK_SIZE_EXCEEDED);
        }
    }

    private function checkPermissions(FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $updateDto->getAsset());
        foreach ($updateDto->getAuthors() as $author) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_AUTHOR_VIEW, $author);
        }
        foreach ($updateDto->getKeywords() as $keyword) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_KEYWORD_VIEW, $keyword);
        }
    }
}
