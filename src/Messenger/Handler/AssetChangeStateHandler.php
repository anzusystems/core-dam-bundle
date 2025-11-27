<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class AssetChangeStateHandler
{
    public function __construct(
        private AssetRepository $assetRepository,
        private AssetFacade $assetFacade,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    public function __invoke(AssetChangeStateMessage $message): void
    {
        $asset = $this->assetRepository->find($message->getAssetId());

        if (null === $asset) {
            return;
        }

        try {
            match ($asset->getAttributes()->getStatus()) {
                AssetStatus::Deleting => $this->assetFacade->delete($asset),
                default => $this->damLogger->info(
                    DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                    sprintf(
                        'Asset (%s) change state to (%s) not suitable for handle',
                        (string) $asset->getId(),
                        $asset->getAttributes()->getStatus()->toString()
                    ),
                )
            };
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                sprintf(
                    'Asset (%s) change state to (%s) failed',
                    (string) $asset->getId(),
                    $asset->getAttributes()->getStatus()->toString()
                ),
                exception: $e
            );

            throw new RuntimeException(message: $e->getMessage(), previous: $e);
        }
    }
}
