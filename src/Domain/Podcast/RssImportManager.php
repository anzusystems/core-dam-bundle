<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Helper\HtmlHelper;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Exception;
use Throwable;

final class RssImportManager
{
    use OutputUtilTrait;

    public function __construct(
        private readonly RssClient $client,
        private readonly AssetRepository $assetRepository,
        private readonly PodcastRepository $podcastRepository,
        private readonly PodcastStatusManager $podcastStatusManager,
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly DamLogger $damLogger,
        private readonly PodcastEpisodeRepository $podcastEpisodeRepository,
        private readonly PodcastRssReader $reader,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function readAllPodcastRss(): void
    {
        $progressBar = $this->outputUtil->createProgressBar();
        $progressBar->start();

        foreach ($this->podcastRepository->findAllToImport() as $podcast) {
            try {
                $this->syncPodcast($podcast);
            } catch (Throwable $exception) {
                $this->damLogger->error(
                    DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                    sprintf('Rss import failed (%s)', $exception->getMessage())
                );
                $this->podcastStatusManager->toImportFailed($podcast);
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->outputUtil->writeln('');
    }

    /**
     * @throws SerializerException
     * @throws Exception
     */
    public function syncPodcast(Podcast $podcast): void
    {
        $this->reader->initReader($this->client->readPodcastRss($podcast));
        if ($podcast->getAttributes()->getLastImportStatus()->isNot(PodcastLastImportStatus::notImported)) {
            $this->updatePodcast($podcast, $this->reader->readChannel());
        }

        foreach ($this->reader->readItems() as $podcastItem) {
            $episodes = $this->podcastEpisodeRepository->findByTitleAndLicence($podcastItem->getTitle(), $podcast->getLicence());

            if ($episodes->isEmpty()) {
                $this->episodeRssImportManager->createAsset($podcast, $podcastItem);
            }
        }

        $this->podcastStatusManager->toImported($podcast);
    }

    private function updatePodcast(Podcast $podcast, Channel $channel): void
    {
        if (empty($podcast->getTexts()->getTitle())) {
            $podcast->getTexts()->setTitle(
                StringHelper::normalize(
                    input: HtmlHelper::htmlToText(html: $channel->getTitle()),
                    configuration: (new StringNormalizerConfiguration())->setLength(PodcastTexts::TITLE_LENGTH)
                )
            );
        }

        if (empty($podcast->getTexts()->getDescription())) {
            $podcast->getTexts()->setDescription(
                StringHelper::normalize(
                    input: HtmlHelper::htmlToText(html: $channel->getDescription()),
                    configuration: (new StringNormalizerConfiguration())->setLength(PodcastTexts::DESCRIPTION_LENGTH)
                )
            );
        }
    }
}
