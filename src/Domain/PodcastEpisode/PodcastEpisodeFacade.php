<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

final class PodcastEpisodeFacade
{
    use ValidatorAwareTrait;
    use MessageBusAwareTrait;

    public function __construct(
        private readonly PodcastEpisodeManager $podcastManager,
        private readonly AssetChangedEventDispatcher $assetMetadataBulkEventDispatcher,
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

        if ($podcastEpisode->getAsset()) {
            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $podcastEpisode->getAsset()->getId()));
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
        $this->podcastManager->delete($podcastEpisode);

        return true;
    }
}
