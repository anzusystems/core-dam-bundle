<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class RefreshAssetPropertiesHandler
{
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetPropertiesRefresher $refresher,
        private readonly AssetManager $manager,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    public function __invoke(AssetRefreshPropertiesMessage $message): void
    {
        $asset = $this->assetRepository->find($message->getAssetId());
        if (null === $asset) {
            return;
        }

        try {
            $this->manager->beginTransaction();
            $this->manager->updateExisting(asset: $asset, trackModification: false);
            $this->indexManager->index($asset);
            $this->manager->commit();
        } catch (Throwable $e) {
            $this->manager->rollback();
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_PROPERTY_REFRESHER,
                sprintf(
                    'Asset (%s) property refresh failed',
                    (string) $asset->getId(),
                ),
                $e
            );

            throw new RuntimeException(message: $e->getMessage(), previous: $e);
        }
    }
}
