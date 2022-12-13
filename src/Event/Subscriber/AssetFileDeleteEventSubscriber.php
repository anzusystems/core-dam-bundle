<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropCache;
use AnzuSystems\CoreDamBundle\Event\AssetFileDeleteEvent;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use League\Flysystem\FilesystemException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AssetFileDeleteEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CropCache $cropCache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetFileDeleteEvent::class => 'deleteAssetFile',
        ];
    }

    /**
     * @throws FilesystemException
     */
    public function deleteAssetFile(AssetFileDeleteEvent $event): void
    {
        if ($event->getType()->is(AssetType::Image)) {
            if (false === empty($event->getAssetFile()->getFilePath())) {
                $this->cropCache->removeCacheByOriginFilePath(
                    $event->getAssetFile()->getExtSystem(),
                    $event->getAssetFile()->getFilePath()
                );
            }
        }
    }
}
