<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Configuration\YoutubeDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;
use AnzuSystems\CoreDamBundle\Model\Enum\YoutubeVideoPrivacy;
use DateTimeInterface;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoStatus;

final class YoutubeVideoFactory extends AbstractDistributionDtoFactory
{
    public function createVideo(
        AssetFile $assetFile,
        YoutubeDistribution $youtubeDistribution,
        YoutubeDistributionServiceConfiguration $configuration,
    ): Google_Service_YouTube_Video {
        $snippet = new Google_Service_YouTube_VideoSnippet();
        $snippet->setChannelId($configuration->getChannelId());

        $snippet->setTitle($youtubeDistribution->getTexts()->getTitle());
        $snippet->setDescription($youtubeDistribution->getTexts()->getDescription());
        $snippet->setTags($youtubeDistribution->getTexts()->getKeywords());

        $selectedOption = $this->getSelectedOption($assetFile, $youtubeDistribution);
        if ($selectedOption && $selectedOption->isAssignable()) {
            $snippet->setCategoryId($selectedOption->getValue());
        }
        if ($youtubeDistribution->getLanguage()) {
            $snippet->setDefaultLanguage($youtubeDistribution->getLanguage());
        }

        $youtubeVideo = new Google_Service_YouTube_Video();
        $youtubeVideo->setSnippet($snippet);
        $youtubeVideo->setStatus($this->createVideoStatus($youtubeDistribution));

        return $youtubeVideo;
    }

    public function createYoutubeVideoDto(Google_Service_YouTube_Video $video): YoutubeVideoDto
    {
        $youtubeVideoDto = new YoutubeVideoDto();
        $youtubeVideoDto->setId($video->getId());
        $youtubeVideoDto->setUploadStatus($video->getStatus()->getUploadStatus());

        $maxPixels = 0;
        $thumbnails = $video->getSnippet()->getThumbnails();
        $thumbnailVariants = [
            $thumbnails->getDefault(),
            $thumbnails->getMedium(),
            $thumbnails->getHigh(),
            $thumbnails->getStandard(),
            $thumbnails->getMaxres(),
        ];

        foreach ($thumbnailVariants as $item) {
            $width = (int) $item->getWidth();
            $height = (int) $item->getHeight();
            $pixels = $width * $height;

            if ($pixels > $maxPixels) {
                $youtubeVideoDto->setThumbnailUrl($item->getUrl());
                $youtubeVideoDto->setThumbnailWidth($width);
                $youtubeVideoDto->setThumbnailHeight($height);

                $maxPixels = $pixels;
            }
        }

        return $youtubeVideoDto;
    }

    private function createVideoStatus(YoutubeDistribution $distribution): Google_Service_YouTube_VideoStatus
    {
        $status = new Google_Service_YouTube_VideoStatus();
        $status->setEmbeddable($distribution->getFlags()->isEmbeddable());
        $status->setMadeForKids($distribution->getFlags()->isForKids());
        $status->setSelfDeclaredMadeForKids($distribution->getFlags()->isForKids());

        if ($distribution->getPrivacy()->is(YoutubeVideoPrivacy::Dynamic)) {
            $status->setPublishAt($distribution->getPublishAt()?->format(DateTimeInterface::ATOM));

            return $status;
        }

        $status->setPrivacyStatus($distribution->getPrivacy()->toString());

        return $status;
    }
}
