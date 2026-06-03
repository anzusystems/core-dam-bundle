<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Admin-facing podcast-episode CRUD. Membership changes (create/delete) notify linked ext-systems here at
 * the facade — deliberately NOT in {@see PodcastEpisodeManager}, whose seam is also driven by bulk RSS
 * import and the TTS orchestrator (which notify on their own schedule); manager-level notify would over-
 * and double-fire.
 */
final class PodcastEpisodeFacade
{
    use ValidatorAwareTrait;
    use MessageBusAwareTrait;

    public function __construct(
        private readonly PodcastEpisodeManager $podcastManager,
        private readonly AssetChangedEventDispatcher $assetMetadataBulkEventDispatcher,
        private readonly ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws ExceptionInterface
     */
    public function create(PodcastEpisode $podcastEpisode): PodcastEpisode
    {
        $this->validator->validate($podcastEpisode);
        $this->podcastManager->create($podcastEpisode);

        // New podcast membership for the asset — refresh its properties and notify linked ext-systems.
        if ($podcastEpisode->getAsset()) {
            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $podcastEpisode->getAsset()->getId()));
            $this->extSystemCallbackFacade->notifyAssetChanged($podcastEpisode->getAsset());
        }

        return $podcastEpisode;
    }

    /**
     * @throws ValidationException
     */
    public function update(PodcastEpisode $podcastEpisode, PodcastEpisode $newPodcastEpisode): PodcastEpisode
    {
        $this->validator->validate($newPodcastEpisode, $podcastEpisode);
        $changedImagePreview = $podcastEpisode->getImagePreview()?->getImageFile()->getId() !== $newPodcastEpisode->getImagePreview()?->getImageFile()->getId();
        $changedRssUrl = $podcastEpisode->getAttributes()->getRssUrl() !== $newPodcastEpisode->getAttributes()->getRssUrl();
        $this->podcastManager->update($podcastEpisode, $newPodcastEpisode);

        $asset = $podcastEpisode->getAsset();
        if (($changedImagePreview || $changedRssUrl) && $asset) {
            $this->assetMetadataBulkEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection([$asset]));
        }

        return $podcastEpisode;
    }

    public function delete(PodcastEpisode $podcastEpisode): bool
    {
        $asset = $podcastEpisode->getAsset();
        $this->podcastManager->delete($podcastEpisode);

        // Removed podcast membership — notify linked ext-systems the asset changed.
        if (null !== $asset) {
            $this->extSystemCallbackFacade->notifyAssetChanged($asset);
        }

        return true;
    }
}
