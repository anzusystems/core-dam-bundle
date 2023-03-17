<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Helper\HtmlHelper;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class RssImportManager
{
    use OutputUtilTrait;
    private const BULK_SIZE = 2;

    public function __construct(
        private readonly PodcastStatusManager $podcastStatusManager,
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
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
