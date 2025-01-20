<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CoreDamBundle\Distribution\Modules\AbstractCustomDataFactory;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionCustomData;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;

final class YoutubeCustomDataFactory extends AbstractCustomDataFactory
{
    public function createDistributionData(YoutubeVideoDto $video): array
    {
        /** @var array $array */
        $array = $this->serializer->toArray(
            (new YoutubeDistributionCustomData())
                ->getThumbnail()->setValue($video->getThumbnailUrl())
        );

        return $array;
    }

    public function getCustomData(Distribution $distribution): YoutubeDistributionCustomData
    {
        /** @var YoutubeDistributionCustomData $customData */
        $customData = $this->serializer->fromArray(
            $distribution->getDistributionData(),
            YoutubeDistributionCustomData::class
        );

        return $customData;
    }
}
