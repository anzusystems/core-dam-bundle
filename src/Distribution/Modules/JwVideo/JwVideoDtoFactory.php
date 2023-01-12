<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaUploadDto;

final class JwVideoDtoFactory extends AbstractDistributionDtoFactory
{
    public function createVideoDtoFromJwVideo(AssetFile $assetFile, JwDistribution $jwDistribution): JwVideoMediaUploadDto
    {
        $jwVideoDto = new JwVideoMediaUploadDto();
        $jwVideoDto->getMetadata()
            ->setTitle($jwDistribution->getTexts()->getTitle())
            ->setDescription($jwDistribution->getTexts()->getDescription())
            ->setAuthor($jwDistribution->getTexts()->getAuthor())
            ->setTags($jwDistribution->getTexts()->getKeywords());

        $selectedOption = $this->getSelectedOption($assetFile, $jwDistribution);
        if ($selectedOption && $selectedOption->isAssignable()) {
            $jwVideoDto->getMetadata()
                ->setCategory($selectedOption->getValue());
        }

        return $jwVideoDto;
    }

    public function createThumbnailUrl(JwDistribution $jwDistribution, ?int $width = null): string
    {
        $url = "https://cdn.jwplayer.com/v2/media/{$jwDistribution->getExtId()}/poster.jpg";
        if ($width) {
            $url .= "?width={$width}";
        }

        return $url;
    }
}
