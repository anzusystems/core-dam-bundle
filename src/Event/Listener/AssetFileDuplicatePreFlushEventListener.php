<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Listener;

use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Event\AssetFileDuplicatePreFlushEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AssetFileDuplicatePreFlushEvent::class)]
final readonly class AssetFileDuplicatePreFlushEventListener
{
    public function __construct(
        private PodcastEpisodeManager $manager,
    ) {
    }

    public function __invoke(AssetFileDuplicatePreFlushEvent $event): void
    {
        $this->manager->moveEpisodes(
            fromAsset: $event->getAssetFile()->getAsset(),
            toAsset: $event->getOriginAssetFile()->getAsset(),
            flush: false
        );
    }
}
