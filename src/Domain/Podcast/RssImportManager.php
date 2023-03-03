<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\Job\JobPodcastSynchronizerFactory;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Helper\HtmlHelper;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\JobPodcastSynchronizerRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\EntityManagerInterface;

final class RssImportManager
{
    use OutputUtilTrait;
    private const BULK_SIZE = 2;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly PodcastRepository $podcastRepository,
        private readonly PodcastStatusManager $podcastStatusManager,
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly DamLogger $damLogger,
        private readonly PodcastRssReader $reader,
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly EntityManagerInterface $manager,
        private readonly ImagePreviewFactory $imagePreviewFactory,
        private readonly JobPodcastSynchronizerFactory $jobPodcastSynchronizerFactory,
        private readonly JobPodcastSynchronizerRepository $podcastSynchronizerRepository,
    ) {
    }

    public function generateImportJobs(bool $fullImport = true): void
    {
        $lastId = null;

        while ($podcast = $this->podcastRepository->findOneToImport($lastId)) {
            $lastId = (string) $podcast->getId();
            $notFinished = $this->podcastSynchronizerRepository->findOneNotFinishedByPodcast($lastId);

            if ($notFinished) {
                $this->outputUtil->writeln(sprintf('Another JOB with id (%s) in queue', $lastId));

                continue;
            }

            $this->jobPodcastSynchronizerFactory->createPodcastSynchronizerJob(
                podcastId: $lastId,
                fullSync: $fullImport
            );
        }
    }

    /**
     * @throws SerializerException
     */
    public function syncPodcast(Podcast $podcast, Channel $channel): void
    {
        if (false === empty($channel->getItunes()->getImage())) {
            $podcast->setImagePreview(
                $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $this->imageDownloadFacade->downloadSynchronous(
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

        $this->podcastStatusManager->toImported($podcast);
    }
}
