<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\Modules\AbstractCustomDataFactory;
use AnzuSystems\CoreDamBundle\Entity\Distribution;

final class JwPlayerCustomDataFactory extends AbstractCustomDataFactory
{
    public const string THUMBNAIL_DATA = 'thumbnail';

    public function __construct(
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
    ) {
    }

    public function createDistributionData(Distribution $distribution): array
    {
        return [
            self::THUMBNAIL_DATA => $this->createUrl(
                $this->jwVideoDtoFactory->createThumbnailUrl($distribution->getExtId())
            ),
        ];
    }

    public function getUrl(Distribution $distribution): ?string
    {
        return $this->getStringValue($distribution->getDistributionData(), self::THUMBNAIL_DATA);
    }
}
