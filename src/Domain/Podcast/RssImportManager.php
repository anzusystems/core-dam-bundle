<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Helper\HtmlHelper;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly PodcastRssReader $reader,
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly EntityManagerInterface $manager,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function readAllPodcastRss(): void
    {
        $podcast = $this->podcastRepository->findOneToImport();
        while ($podcast) {
            $lastId = $podcast->getId();

            try {
                $this->syncPodcast($podcast);
            } catch (Throwable $exception) {
                $this->damLogger->error(
                    DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                    sprintf('Podcast import failed (%s)', $exception->getMessage())
                );
                $this->podcastStatusManager->toImportFailed($podcast);
            }

            $this->manager->clear();
            $podcast = $this->podcastRepository->findOneToImport($lastId);
        }
    }

    /**
     * @throws SerializerException
     * @throws Exception
     */
    public function syncPodcast(Podcast $podcast): void
    {
        $this->reader->initReader($this->client->readPodcastRss($podcast));

        $this->outputUtil->writeln(sprintf('Importing podcast (%s)', $podcast->getTexts()->getTitle()));
        if ($podcast->getAttributes()->getLastImportStatus()->is(PodcastLastImportStatus::notImported)) {
            $this->outputUtil->writeln('Updating podcast metadata');
            $this->updatePodcast($podcast, $this->reader->readChannel());
        }

        $progressBar = $this->outputUtil->createProgressBar();
        $progressBar->start();

        $createdItems = 0;
        foreach ($this->reader->readItems() as $podcastItem) {
            if ($this->importEpisode($podcast, $podcastItem)) {
                $createdItems++;
            }
            $progressBar->advance();
        }

        $this->podcastStatusManager->toImported($podcast);

        $progressBar->finish();
        $this->outputUtil->writeln(sprintf('Created items (%d)', $createdItems));
    }

    /**
     * @throws SerializerException
     */
    private function importEpisode(Podcast $podcast, Item $podcastItem): bool
    {
        try {
            $this->episodeRssImportManager->importEpisode($podcast, $podcastItem);

            return true;
        } catch (Throwable $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf(
                    'Podcast episode (%s) import failed (%s)',
                    $podcastItem->getTitle(),
                    $exception->getMessage()
                )
            );
        }

        return false;
    }

    private function updatePodcast(Podcast $podcast, Channel $channel): void
    {
        if (false === empty($channel->getItunes()->getImage())) {
            $podcast->setImagePreview(
                $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $this->imageDownloadFacade->download(
                        assetLicence: $podcast->getLicence(),
                        url: $channel->getItunes()->getImage()
                    ),
                    flush: false
                )
            );
        }

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
