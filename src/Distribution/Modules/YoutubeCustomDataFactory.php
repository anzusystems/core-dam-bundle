<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;

final class YoutubeCustomDataFactory extends AbstractCustomDataFactory
{
    public const THUMBNAIL_DATA = 'thumbnail';

    public function createDistributionData(YoutubeVideoDto $video): array
    {
        return [
            self::THUMBNAIL_DATA => $this->createUrl($video->getThumbnailUrl()),
        ];
    }

    public function getUrl(Distribution $distribution): ?string
    {
        return $this->getStringValue($distribution->getDistributionData(), self::THUMBNAIL_DATA);
    }
}
