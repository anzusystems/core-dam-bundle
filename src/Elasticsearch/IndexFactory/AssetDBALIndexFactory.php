<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Image\ClosestColorProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageOrientation;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use DateTimeImmutable;

final readonly class AssetDBALIndexFactory implements DBALIndexFactoryInterface
{
    public function __construct(
        private ClosestColorProvider $closestColorProvider,
    ) {
    }

    /**
     * @param array{
     *     id: int,
     *     main_file_id: int,
     *     ext_system_id: int,
     *     attributes_asset_type: string,
     *     attributes_status: string,
     *     asset_flags_described: int,
     *     asset_flags_visible: int,
     *     asset_flags_generated_by_system: int,
     *     asset_file_properties_slot_names: string,
     *     asset_file_properties_distributes_in_services: string,
     *     asset_file_properties_from_rss: int,
     *     asset_file_properties_width: int,
     *     asset_file_properties_height: int,
     *     created_at: DateTimeImmutable,
     *     modified_at: DateTimeImmutable,
     *     created_by_id: int,
     *     licence_id: int,
     *     asset_attributes_origin_file_name: string,
     *     asset_attributes_mime_type: string,
     *     asset_attributes_size: int,
     *     image_attributes_rotation: int,
     *     image_attributes_most_dominant_color: ?Color,
     *     image_attributes_width: int,
     *     image_attributes_height: int,
     *     attributes_rotation: int,
     *     video_attributes_duration: int,
     *     attributes_width: int,
     *     attributes_height: int,
     *     video_codec_name: string,
     *     video_attributes_bitrate: int,
     *     attributes_page_count: int,
     *     audio_attributes_duration: int,
     *     audio_attributes_codec_name: string,
     *     audio_attributes_bitrate: int,
     *     flags_single_use: int,
     *     custom_data: array<string, ?string>,
     *     keyword_ids: string[],
     *     author_ids: string[],
     *     file_ids: string[],
     *     podcast_ids: string[],
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

        $width = (int) $array['asset_file_properties_width'];
        $height = (int) $array['asset_file_properties_height'];

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
            ...$array['custom_data'],
            ...$this->getSpecificFields($array),
        ];
    }

    private function getSpecificFields(array $array): array
    {
        $width = (int) $array['asset_file_properties_width'];
        $height = (int) $array['asset_file_properties_height'];

        return match ($array['attributes_asset_type']) {
            AssetType::IMAGE => [
                'rotation' => $array['image_attributes_rotation'],
                'mostDominantColor' => $array['image_attributes_most_dominant_color']?->toString(),
                'closestMostDominantColor' => $array['image_attributes_most_dominant_color'] instanceof Color
                    ? $this->closestColorProvider->provideClosestColor($array['image_attributes_most_dominant_color'])->toString()
                    : null,
                'orientation' => ImageOrientation::getOrientation($width, $height)->toString(),
            ],
            AssetType::DOCUMENT => [
                'pageCount' => $array['attributes_page_count'],
            ],
            AssetType::AUDIO => [
                'duration' => $array['audio_attributes_duration'],
                'codecName' => $array['audio_attributes_codec_name'],
                'bitrate' => $array['audio_attributes_bitrate'],
                'podcastIds' => $array['podcast_ids'],
                'inPodcast' => false === empty($array['podcast_ids']),
            ],
            AssetType::VIDEO => [
                'rotation' => $array['attributes_rotation'],
                'duration' => $array['video_attributes_duration'],
                'codecName' => $array['video_codec_name'],
                'bitrate' => $array['video_attributes_bitrate'],
                'orientation' => ImageOrientation::getOrientation($width, $height)->toString(),
            ],
            default => [],
        };
    }
}
