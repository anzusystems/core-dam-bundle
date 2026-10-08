<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;

final class AssetListViewDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'asset_list_view';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @param list<int> $licenceIds
     *
     * @throws Exception
     */
    public function deleteLicencesUnreachableByOtherGroups(array $licenceIds, int $excludedGroupId): void
    {
        $this->connection->executeStatement(
            <<<SQL
                DELETE licence_in_view
                FROM asset_licence_in_list_view AS licence_in_view
                WHERE licence_in_view.asset_licence_id IN (:licenceIds)
                    AND EXISTS (
                        SELECT 1
                        FROM licence_group_in_list_view AS any_group
                        WHERE any_group.asset_list_view_id = licence_in_view.asset_list_view_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM licence_group_in_list_view AS reaching_group
                        INNER JOIN asset_licence_in_group AS licence_in_group
                            ON licence_in_group.asset_licence_group_id = reaching_group.asset_licence_group_id
                        WHERE reaching_group.asset_list_view_id = licence_in_view.asset_list_view_id
                            AND reaching_group.asset_licence_group_id != :excludedGroupId
                            AND licence_in_group.asset_licence_id = licence_in_view.asset_licence_id
                    )
                SQL,
            [
                'licenceIds' => $licenceIds,
                'excludedGroupId' => $excludedGroupId,
            ],
            [
                'licenceIds' => ArrayParameterType::INTEGER,
            ]
        );
    }

    /**
     * @param list<int> $licenceIds
     *
     * @throws Exception
     */
    public function clearUploadLicenceNotInView(array $licenceIds): void
    {
        $this->connection->executeStatement(
            <<<SQL
                UPDATE asset_list_view AS view
                SET view.upload_licence_id = NULL
                WHERE view.upload_licence_id IN (:licenceIds)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM asset_licence_in_list_view AS licence_in_view
                        WHERE licence_in_view.asset_list_view_id = view.id
                            AND licence_in_view.asset_licence_id = view.upload_licence_id
                    )
                SQL,
            [
                'licenceIds' => $licenceIds,
            ],
            [
                'licenceIds' => ArrayParameterType::INTEGER,
            ]
        );
    }
}
