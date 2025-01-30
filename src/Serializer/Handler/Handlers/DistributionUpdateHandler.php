<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionAdmUpdateDto;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use AnzuSystems\SerializerBundle\Serializer;

class DistributionUpdateHandler extends AbstractHandler
{
    public function __construct(
        private readonly Serializer $serializer,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): array
    {
        return $this->serializer->toArray($value);
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        if (false === is_array($value)) {
            return [];
        }

        $data = [];
        foreach ($value as $item) {
            if (isset($item['_resourceName'])  && $item['_resourceName'] === JwDistribution::getResourceName()) {
                $data[] = $this->serializer->fromArray($item, JwDistributionAdmUpdateDto::class);
            }
            if (isset($item['_resourceName'])  && $item['_resourceName'] === YoutubeDistribution::getResourceName()) {
                $data[] = $this->serializer->fromArray($item, YoutubeDistributionAdmUpdateDto::class);
            }
        }

        return $data;
    }
}
