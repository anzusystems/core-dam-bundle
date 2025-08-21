<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetMetadataBulkEventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;

final class PodcastEpisodeFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly PodcastEpisodeManager $podcastManager,
        private readonly AssetMetadataBulkEventDispatcher $assetMetadataBulkEventDispatcher,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(PodcastEpisode $podcastEpisode): PodcastEpisode
    {
        $this->validator->validate($podcastEpisode);
        $this->podcastManager->create($podcastEpisode);

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
            $this->assetMetadataBulkEventDispatcher->dispatchAssetMetadataBulkChanged(new ArrayCollection([$asset]));
        }

        return $podcastEpisode;
    }

    public function delete(PodcastEpisode $podcastEpisode): bool
    {
        $this->podcastManager->delete($podcastEpisode);

        return true;
    }
}
