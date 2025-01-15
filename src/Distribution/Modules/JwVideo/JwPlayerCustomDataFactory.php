<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\Modules\AbstractCustomDataFactory;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionCustomData;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class JwPlayerCustomDataFactory extends AbstractCustomDataFactory
{
    public function __construct(
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function createDistributionData(Distribution $distribution): array
    {
        /** @var array $array */
        $array = $this->serializer->toArray(
            (new JwDistributionCustomData())
                ->getThumbnail()->setValue( $this->jwVideoDtoFactory->createThumbnailUrl($distribution->getExtId()))
        );

        return $array;
    }

    public function getCustomData(Distribution $distribution): JwDistributionCustomData
    {
        return $this->serializer->fromArray(
            $distribution->getDistributionData(),
            JwDistributionCustomData::class
        );
    }
}
