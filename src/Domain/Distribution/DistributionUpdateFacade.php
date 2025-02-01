<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\AbstractDistributionUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\CustomDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Throwable;

final class DistributionUpdateFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly DistributionRepository $distributionRepository,
        private readonly JwDistributionUpdateFacade $jwDistributionUpdateFacade,
        private readonly YoutubeDistributionUpdateFacade $youtubeDistributionUpdateFacade,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly CustomDistributionUpdateFacade $customDistributionUpdateFacade,
        private readonly AssetManager $assetManager,
        private readonly JwDistributionManager $jwDistributionManager,
        private readonly AssetRepository $assetRepository,
        private readonly AccessDenier $accessDenier,
    ) {
    }

    public function delete(Distribution $distribution): void
    {
        $asset = $this->assetRepository->find($distribution->getAssetId());
        $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_READ, $distribution->getAssetFile());

        $this->assetManager->beginTransaction();
        try {

            $this->jwDistributionManager->delete($distribution);
            if ($asset instanceof Asset) {
                $this->assetManager->updateExisting($asset);
                $this->indexManager->index($asset);
            }

            $this->assetManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetManager->isTransactionActive()) {
                $this->assetManager->rollback();
            }

            throw new RuntimeException('distribution_delete_failed', previous: $exception);
        }
    }

    /**
     * @throws ValidationException
     */
    public function upsert(Asset $asset, AbstractDistributionUpdateDto $dto): AbstractDistributionUpdateDto
    {
        $this->validator->validate($dto);
        $this->validateDistributionService($dto);
        $this->validateAsset($dto);
        if (null === $dto->getDistribution()) {
            $this->checksDuplicity($dto);
        }

        // validate permissions
        $this->assetManager->beginTransaction();

        try {
            $this->doUpsert($dto);
            $this->assetManager->updateExisting($asset);
            $this->indexManager->index($asset);
            $this->assetManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetManager->isTransactionActive()) {
                $this->assetManager->rollback();
            }

            throw new RuntimeException('distribution_upsert_failed', previous: $exception);
        }

        return $dto;
    }

    /**
     * @throws ValidationException
     */
    private function doUpsert(AbstractDistributionUpdateDto $dto): bool
    {
        if ($dto instanceof YoutubeDistributionAdmUpdateDto) {
            $this->youtubeDistributionUpdateFacade->upsert($dto);

            return true;
        }
        if ($dto instanceof JwDistributionAdmUpdateDto) {
            $this->jwDistributionUpdateFacade->upsert($dto);

            return true;
        }
        if ($dto instanceof CustomDistributionAdmUpdateDto && $dto->getDistribution() instanceof Distribution) {
            $this->customDistributionUpdateFacade->upsert($dto, $dto->getDistribution());

            return true;
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    private function checksDuplicity(AbstractDistributionUpdateDto $dto): void
    {
        $distribution = $this->distributionRepository->findByAssetFileAndDistributionService(
            (string) $dto->getAssetFile()->getId(),
            $dto->getDistributionService()
        );

        if ($distribution instanceof Distribution) {
            throw (new ValidationException())
                ->addFormattedError('distributionService', ValidationException::ERROR_FIELD_UNIQUE);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateDistributionService(AbstractDistributionUpdateDto $dto): void
    {
        $service = $this->distributionConfigurationProvider->getDistributionService($dto->getDistributionService());

        if ($dto instanceof YoutubeDistributionAdmUpdateDto && $service->isYoutubeDistributionService()) {
            return;
        }
        if ($dto instanceof JwDistributionAdmUpdateDto && $service->isJwDistributionService()) {
            return;
        }
        if ($service->isCustomDistributionService()) {
            return;
        }

        throw (new ValidationException())
            ->addFormattedError('distributionService', ValidationException::ERROR_FIELD_INVALID);
    }

    /**
     * @throws ValidationException
     */
    private function validateAsset(AbstractDistributionUpdateDto $dto): void
    {
        if ($dto->getAsset()->isNot($dto->getAssetFile()->getAsset())) {
            throw (new ValidationException())
                ->addFormattedError('assetFile', ValidationException::ERROR_ID_MISMATCH);
        }
    }
}
