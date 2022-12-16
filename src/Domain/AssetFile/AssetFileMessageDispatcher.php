<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DocumentFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class AssetFileMessageDispatcher
{
    public function __construct(
        protected MessageBusInterface $messageBus,
    ) {
    }

    public function dispatchAssetFileChangeState(AssetFile $assetFile): void
    {
        $this->messageBus->dispatch(
            match ($assetFile->getAssetType()) {
                AssetType::Image => new ImageFileChangeStateMessage($assetFile),
                AssetType::Video => new VideoFileChangeStateMessage($assetFile),
                AssetType::Audio => new AudioFileChangeStateMessage($assetFile),
                AssetType::Document => new DocumentFileChangeStateMessage($assetFile),
            }
        );
    }
}
