<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\AssetSearchableElementsCache;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\RebuildIndexConfig;
use AnzuSystems\CoreDamBundle\Elasticsearch\Repository\DBALIndexableRepositoryInterface;
use AnzuSystems\CoreDamBundle\Helper\DateTimeHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Throwable;

final class AssetDBALRepository extends AbstractAnzuDBALRepository implements DBALIndexableRepositoryInterface
{
    private const string TABLE_NAME = 'asset';

    public function __construct(
        private readonly AssetKeywordDBALRepository $assetKeywordRepository,
        private readonly AssetAuthorDBALRepository $assetAuthorRepository,
        private readonly AssetAssetSlotDBALRepository $assetAssetSlotRepository,
        private readonly AssetPodcastDBALRepository $assetPodcastRepository,
        private readonly AssetSearchableElementsCache $assetSearchableElementsCache,
    ) {
    }

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getAllCountForIndexRebuild(RebuildIndexConfig $config): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(entity.id)')
            ->from(self::TABLE_NAME, 'entity')
        ;
        $this->applyRebuildIndexConfig($qb, $config);

        try {
            return (int) $qb->fetchOne();
        } catch (Throwable) {
            return App::ZERO;
        }
    }

    public function getAllForIndexRebuild(RebuildIndexConfig $config): array
    {
        $qb = $this->connection->createQueryBuilder();
        /** @noinspection PhpDqlBuilderUnknownModelInspection */
        $qb
            ->select('
                entity.id, entity.main_file_id, entity.ext_system_id,
                entity.attributes_asset_type, entity.attributes_status,
                entity.asset_flags_described, entity.asset_flags_visible, entity.asset_flags_generated_by_system,
                entity.asset_file_properties_slot_names, entity.asset_file_properties_distributes_in_services,
                entity.asset_file_properties_from_rss, entity.asset_file_properties_width, entity.asset_file_properties_height,
                entity.created_at, entity.modified_at,
                entity.created_by_id, entity.licence_id,
                asf.asset_attributes_origin_file_name, asf.asset_attributes_mime_type, asf.asset_attributes_size,
                image.image_attributes_rotation, image.image_attributes_most_dominant_color, image.image_attributes_width, image.image_attributes_height,
                video.attributes_rotation, video.attributes_duration video_attributes_duration, video.attributes_width, 
                video.attributes_height, video.attributes_codec_name video_codec_name, video.attributes_bitrate video_attributes_bitrate,
                document.attributes_page_count,
                audio.attributes_duration audio_attributes_duration, audio.attributes_codec_name audio_attributes_codec_name,
                audio.attributes_bitrate audio_attributes_bitrate,
                metadata.custom_data,
                asf.flags_single_use
            ')
            ->from(self::TABLE_NAME, 'entity')
            ->leftJoin('entity', 'asset_file', 'asf', 'asf.id = entity.main_file_id')
            ->leftJoin('entity', 'asset_metadata', 'metadata', 'metadata.id = entity.metadata_id')
            ->leftJoin('entity', 'video_file', 'video', 'video.id = asf.id')
            ->leftJoin('entity', 'image_file', 'image', 'image.id = asf.id')
            ->leftJoin('entity', 'document_file', 'document', 'document.id = asf.id')
            ->leftJoin('entity', 'audio_file', 'audio', 'audio.id = asf.id')
            ->setMaxResults($config->getBatchSize())
            ->orderBy('id', Order::Ascending->value)
        ;
        $this->applyRebuildIndexConfig($qb, $config);

        $data = $qb->fetchAllAssociative();

        $ids = [];

        foreach ($data as $item) {
            $ids[] = $item['id'];
        }

        $keywordMap = $this->assetKeywordRepository->getByAsset($ids);
        $authorMap = $this->assetAuthorRepository->getByAsset($ids);
        $assetSlotMap = $this->assetAssetSlotRepository->getByAsset($ids);

        foreach ($data as $index => $item) {
            $data[$index]['custom_data'] = $this->getSearchableCustomData(
                json_decode($item['custom_data'], true),
                $item['ext_system_id'],
                $item['attributes_asset_type']
            );

            $data[$index]['created_at'] = DateTimeHelper::datetimeOrNull($item['created_at']);
            $data[$index]['modified_at'] = DateTimeHelper::datetimeOrNull($item['modified_at']);
            $data[$index]['keyword_ids'] = $keywordMap[$item['id']]['ids'] ?? [];
            $data[$index]['author_ids'] = $authorMap[$item['id']]['ids'] ?? [];
            $data[$index]['file_ids'] = $assetSlotMap[$item['id']]['ids'] ?? [];
            $data[$index]['image_attributes_most_dominant_color'] = is_string($item['image_attributes_most_dominant_color'])
                ? Color::fromString($item['image_attributes_most_dominant_color'])
                : null;

            $data[$index]['podcast_ids'] = AssetType::AUDIO === $item['attributes_asset_type']
                ? $this->assetPodcastRepository->getByAsset($item['id'])
                : [];
        }

        return $data;
    }

    protected function applyRebuildIndexConfig(ORMQueryBuilder|QueryBuilder $qb, RebuildIndexConfig $config): ORMQueryBuilder|QueryBuilder
    {
        $qb->andWhere('entity.ext_system_id = :extSystemId')
            ->setParameter('extSystemId', $config->getCurrentExtSystemId());

        if ($config->hasIdFrom() || $config->hasLastProcessedId()) {
            $idFromCompareCharacter = $config->hasLastProcessedId() ? '>' : '>=';
            $idFrom = $config->hasLastProcessedId() ? $config->getLastProcessedId() : $config->getIdFrom();
            $qb->andWhere("entity.id {$idFromCompareCharacter} :idFrom")
                ->setParameter('idFrom', $idFrom);
        }
        if ($config->hasIdUntil()) {
            $qb->andWhere('entity.id <= :idUntil')
                ->setParameter('idUntil', $config->getIdUntil());
        }

        return $qb;
    }

    private function getSearchableCustomData(array $customData, int $extSystemId, string $assetType): array
    {
        $properties = $this->assetSearchableElementsCache->getSearchableCustomFormProperties(
            $extSystemId,
            $assetType
        );

        $searchableCustomData = [];
        foreach ($properties as $property) {
            $searchableCustomData[CustomDataIndexDefinitionFactory::getIndexKeyNameByProperty($property)] =
                $customData[$property] ?? null;
        }

        if (AssetType::IMAGE === $assetType) {
            $searchableCustomData = StringIndexBuilder::optimizeImageCustomData($searchableCustomData);
        }

        return $searchableCustomData;
    }
}
