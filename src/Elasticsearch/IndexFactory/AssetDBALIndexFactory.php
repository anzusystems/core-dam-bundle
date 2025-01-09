<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Image\ClosestColorProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageOrientation;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;

final readonly class AssetDBALIndexFactory implements DBALIndexFactoryInterface
{
    public function __construct(
        private ClosestColorProvider $closestColorProvider,
    ) {
    }

    /**
     * @param array{
     *     id: int,
     * } $array
     */
    public function buildFromArray(
        array $array
    ): array {
        $slotNames = array_values(json_decode($array['asset_file_properties_slot_names'], true));
        $distributedInServices = array_values(json_decode($array['asset_file_properties_distributes_in_services'], true));

        $gcd = Math::getGreatestCommonDivisor(
            $array['asset_file_properties_width'],
            $array['asset_file_properties_height'],
        );

        $width = $array['asset_file_properties_width'];
        $height = $array['asset_file_properties_height'];

        return [
            'id' => $array['id'],
            'mainFileId' => $array['main_file_id'],
            'fileIds' => $array['file_ids'],
            'keywordIds' => $array['keyword_ids'],
            'authorIds' => $array['author_ids'],
            'type' => $array['attributes_asset_type'],
            'status' => $array['attributes_status'],
            'described' => (bool) $array['asset_flags_described'],
            'visible' => (bool) $array['asset_flags_visible'],
            'slotsCount' => count($slotNames),
            'slotNames' => $slotNames,
            'generatedBySystem' => (bool) $array['asset_flags_generated_by_system'],
            'createdAt' => $array['created_at']->getTimestamp(),
            'modifiedAt' => $array['modified_at']->getTimestamp(),
            'createdById' => $array['created_by_id'],
            'licence' => $array['licence_id'],
            'distributedInServices' => $distributedInServices,
            'fromRss' => (bool) $array['asset_file_properties_from_rss'],
            'pixelSize' => $width * $height,
            'width' => $width,
            'height' => $height,
            'mainFileSingleUse' => (bool) $array['flags_single_use'],
            'ratioWidth' => App::ZERO < $gcd ? (int) ($width / $gcd) : App::ZERO,
            'ratioHeight' => App::ZERO < $gcd ? (int) ($height / $gcd) : App::ZERO,
            // AssetFile
            'originFileName' => $array['asset_attributes_origin_file_name'],
            'mimeType' => $array['asset_attributes_mime_type'],
            'size' => $array['asset_attributes_size'],
            // AssetFileSpecifics
            'rotation' => $array['image_attributes_rotation'] ?? $array['attributes_rotation'],
            'mostDominantColor' => $array['image_attributes_most_dominant_color']?->toString(),
            'closestMostDominantColor' => $array['image_attributes_most_dominant_color'] instanceof Color
                ? $this->closestColorProvider->provideClosestColor($array['image_attributes_most_dominant_color'])
                : null,
            'orientation' => ImageOrientation::getOrientation($width, $height)->toString(),
            'pageCount' => $array['attributes_page_count'],
            'duration' => $array['video_attributes_duration'] ?? $array['audio_attributes_duration'],
            'codecName' => $array['video_codec_name'] ?? $array['audio_attributes_codec_name'],
            'bitrate' => $array['video_attributes_bitrate'] ?? $array['audio_attributes_bitrate'],
            'podcastIds' => $array['podcast_ids'],
            'inPodcast' => false === empty($array['podcast_ids']),
        ];
    }
}
