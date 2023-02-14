<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFileMetadata;

use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\TimeTrackingDtoTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\UserTrackingDtoTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\UuidIdentityDtoTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileMetadataAdmDetailDto
{
    use UuidIdentityDtoTrait;
    use TimeTrackingDtoTrait;
    use UserTrackingDtoTrait;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $exifData;

    public static function getInstance(AssetFileMetadata $fileMetadata): self
    {
        return (new self())
            ->setId((string) $fileMetadata->getId())
            ->setCreatedAt($fileMetadata->getCreatedAt())
            ->setModifiedAt($fileMetadata->getModifiedAt())
            ->setCreatedBy($fileMetadata->getCreatedBy())
            ->setModifiedBy($fileMetadata->getModifiedBy())
            ->setExifData($fileMetadata->getExifData())
        ;
    }

    public function getExifData(): array
    {
        return $this->exifData;
    }

    public function setExifData(array $exifData): self
    {
        $this->exifData = $exifData;

        return $this;
    }
}
