<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Elasticsearch\CustomData\AssetMetadataCustomData;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Image\ClosestColorProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageOrientation;
use Doctrine\ORM\NonUniqueResultException;

final class AssetIndexFactory implements IndexFactoryInterface
{
    public function __construct(
        private readonly AssetMetadataCustomData $assetMetadataCustomData,
        private readonly ClosestColorProvider $closestColorProvider,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return Asset::class;
    }

    /**
     * @param Asset $entity
     *
     * @throws NonUniqueResultException
     */
    public function buildFromEntity(ExtSystemIndexableInterface $entity): array
    {
        $gcd = Math::getGreatestCommonDivisor(
            $entity->getAssetFileProperties()->getWidth(),
            $entity->getAssetFileProperties()->getHeight(),
        );

        return [
            'id' => $entity->getId(),
            'fileIds' => CollectionHelper::traversableToIds(
                $entity->getSlots(),
                fn (AssetSlot $slot): string => (string) $slot->getAssetFile()->getId()
            ),
            'type' => $entity->getAttributes()->getAssetType()->toString(),
            'status' => $entity->getAttributes()->getStatus(),
            'described' => $entity->getAssetFlags()->isDescribed(),
            'visible' => $entity->getAssetFlags()->isVisible(),
            'slotsCount' => count($entity->getAssetFileProperties()->getSlotNames()),
            'generatedBySystem' => $entity->getAssetFlags()->isGeneratedBySystem(),
            'modifiedAt' => $entity->getModifiedAt()->getTimestamp(),
            'createdAt' => $entity->getCreatedAt()->getTimestamp(),
            'licence' => $entity->getLicence()->getId(),
            'distributedInServices' => $entity->getAssetFileProperties()->getDistributesInServices(),
            'slotNames' => $entity->getAssetFileProperties()->getSlotNames(),
            'fromRss' => $entity->getAssetFileProperties()->isFromRss(),
            'pixelSize' => $entity->getAssetFileProperties()->getWidth() * $entity->getAssetFileProperties()->getHeight(),
            'width' => $entity->getAssetFileProperties()->getWidth(),
            'height' => $entity->getAssetFileProperties()->getHeight(),
            'ratioWidth' => App::ZERO < $gcd ? (int) ($entity->getAssetFileProperties()->getWidth() / $gcd) : App::ZERO,
            'ratioHeight' => App::ZERO < $gcd ? (int) ($entity->getAssetFileProperties()->getHeight() / $gcd) : App::ZERO,
            ...$this->assetMetadataCustomData->buildFromEntity($entity),
            ...$this->getSpecificAssetFields($entity),
        ];
    }

    private function getSpecificAssetFields(Asset $entity): array
    {
        $mainFile = $entity->getMainFile();
        if (null === $mainFile) {
            return [];
        }

        $fields = [
            'originFileName' => $mainFile->getAssetAttributes()->getOriginFileName(),
            'mimeType' => $mainFile->getAssetAttributes()->getMimeType(),
            'size' => $mainFile->getAssetAttributes()->getSize(),
        ];

        if ($mainFile instanceof ImageFile) {
            return [
                ...$fields,
                ...$this->getImageFields($mainFile),
            ];
        }
        if ($mainFile instanceof VideoFile) {
            return [
                ...$fields,
                ...$this->getVideoFields($mainFile),
            ];
        }
        if ($mainFile instanceof AudioFile) {
            return [
                ...$fields,
                ...$this->getAudioFields($mainFile),
            ];
        }
        if ($mainFile instanceof DocumentFile) {
            return [
                ...$fields,
                ...$this->getDocumentFields($mainFile),
            ];
        }

        return $fields;
    }

    private function getImageFields(ImageFile $imageFile): array
    {
        return [
            'rotation' => $imageFile->getImageAttributes()->getRotation(),
            'mostDominantColor' => $imageFile->getImageAttributes()->getMostDominantColor()->toString(),
            'closestMostDominantColor' => $this->closestColorProvider->provideClosestColor(
                $imageFile->getImageAttributes()->getMostDominantColor()
            )->toString(),
            'orientation' => ImageOrientation::fromImage($imageFile)->toString(),
        ];
    }

    private function getDocumentFields(DocumentFile $documentFile): array
    {
        return [
            'pageCount' => $documentFile->getAttributes()->getPageCount(),
        ];
    }

    private function getVideoFields(VideoFile $videoFile): array
    {
        return [
            'rotation' => $videoFile->getAttributes()->getRotation(),
            'duration' => $videoFile->getAttributes()->getDuration(),
            'orientation' => ImageOrientation::fromVideo($videoFile)->toString(),
            'codecName' => $videoFile->getAttributes()->getCodecName(),
            'bitrate' => $videoFile->getAttributes()->getBitrate(),

        ];
    }

    private function getAudioFields(AudioFile $audioFile): array
    {
        $podcastIds = $this->getPodcastIds($audioFile);

        return [
            'duration' => $audioFile->getAttributes()->getDuration(),
            'codecName' => $audioFile->getAttributes()->getCodecName(),
            'bitrate' => $audioFile->getAttributes()->getBitrate(),
            'podcastIds' => $podcastIds,
            'inPodcast' => false === empty($podcastIds),
        ];
    }

    private function getPodcastIds(AudioFile $audioFile): array
    {
        return $audioFile->getAsset()->getEpisodes()->map(
            fn (PodcastEpisode $podcastEpisode): string => (string) $podcastEpisode->getPodcast()->getId()
        )->getValues();
    }
}
