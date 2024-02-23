<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Permission;

use AnzuSystems\Contracts\Security\Grant;

class DamPermissions
{
    // Asset
    public const DAM_ASSET_CREATE = 'dam_asset_create';
    public const DAM_ASSET_UPDATE = 'dam_asset_update';
    public const DAM_ASSET_VIEW = 'dam_asset_view';
    public const DAM_ASSET_DELETE = 'dam_asset_delete';

    // Video
    public const DAM_VIDEO_CREATE = 'dam_video_create';
    public const DAM_VIDEO_UPDATE = 'dam_video_update';
    public const DAM_VIDEO_VIEW = 'dam_video_view';
    public const DAM_VIDEO_DELETE = 'dam_video_delete';

    // Audio
    public const DAM_AUDIO_CREATE = 'dam_audio_create';
    public const DAM_AUDIO_UPDATE = 'dam_audio_update';
    public const DAM_AUDIO_VIEW = 'dam_audio_view';
    public const DAM_AUDIO_DELETE = 'dam_audio_delete';

    // Asset Custom Form
    public const DAM_CUSTOM_FORM_CREATE = 'dam_assetCustomForm_create';
    public const DAM_CUSTOM_FORM_UPDATE = 'dam_assetCustomForm_update';
    public const DAM_CUSTOM_FORM_VIEW = 'dam_assetCustomForm_view';

    // Custom Form Element
    public const DAM_CUSTOM_FORM_ELEMENT_VIEW = 'dam_customFormElement_view';

    // Document
    public const DAM_DOCUMENT_CREATE = 'dam_document_create';
    public const DAM_DOCUMENT_UPDATE = 'dam_document_update';
    public const DAM_DOCUMENT_VIEW = 'dam_document_view';
    public const DAM_DOCUMENT_DELETE = 'dam_document_delete';

    // Image
    public const DAM_IMAGE_CREATE = 'dam_image_create';
    public const DAM_IMAGE_UPDATE = 'dam_image_update';
    public const DAM_IMAGE_VIEW = 'dam_image_view';
    public const DAM_IMAGE_DELETE = 'dam_image_delete';

    // Region of Interest
    public const DAM_REGION_OF_INTEREST_CREATE = 'dam_regionOfInterest_create';
    public const DAM_REGION_OF_INTEREST_UPDATE = 'dam_regionOfInterest_update';
    public const DAM_REGION_OF_INTEREST_VIEW = 'dam_regionOfInterest_view';
    public const DAM_REGION_OF_INTEREST_DELETE = 'dam_regionOfInterest_delete';

    // ExtSystem
    public const DAM_EXT_SYSTEM_UPDATE = 'dam_extSystem_update';
    public const DAM_EXT_SYSTEM_VIEW = 'dam_extSystem_view';
    public const DAM_EXT_SYSTEM_LIST = 'dam_extSystem_list';
    public const DAM_EXT_SYSTEM_UI = 'dam_extSystem_ui';

    // AssetLicence
    public const DAM_ASSET_LICENCE_CREATE = 'dam_assetLicence_create';
    public const DAM_ASSET_LICENCE_UPDATE = 'dam_assetLicence_update';
    public const DAM_ASSET_LICENCE_VIEW = 'dam_assetLicence_view';
    public const DAM_ASSET_LICENCE_LIST = 'dam_assetLicence_list';
    public const DAM_ASSET_LICENCE_UI = 'dam_assetLicence_ui';

    // AssetLicenceGroup
    public const DAM_ASSET_LICENCE_GROUP_CREATE = 'dam_assetLicenceGroup_create';
    public const DAM_ASSET_LICENCE_GROUP_UPDATE = 'dam_assetLicenceGroup_update';
    public const DAM_ASSET_LICENCE_GROUP_VIEW = 'dam_assetLicenceGroup_view';
    public const DAM_ASSET_LICENCE_GROUP_LIST = 'dam_assetLicenceGroup_list';
    public const DAM_ASSET_LICENCE_GROUP_UI = 'dam_assetLicenceGroup_ui';

    // Author
    public const DAM_AUTHOR_CREATE = 'dam_author_create';
    public const DAM_AUTHOR_UPDATE = 'dam_author_update';
    public const DAM_AUTHOR_VIEW = 'dam_author_view';
    public const DAM_AUTHOR_DELETE = 'dam_author_delete';
    public const DAM_AUTHOR_UI = 'dam_author_ui';

    // Keyword
    public const DAM_KEYWORD_CREATE = 'dam_keyword_create';
    public const DAM_KEYWORD_UPDATE = 'dam_keyword_update';
    public const DAM_KEYWORD_VIEW = 'dam_keyword_view';
    public const DAM_KEYWORD_DELETE = 'dam_keyword_delete';
    public const DAM_KEYWORD_UI = 'dam_keyword_ui';

    // Podcast
    public const DAM_PODCAST_CREATE = 'dam_podcast_create';
    public const DAM_PODCAST_UPDATE = 'dam_podcast_update';
    public const DAM_PODCAST_VIEW = 'dam_podcast_view';
    public const DAM_PODCAST_DELETE = 'dam_podcast_delete';
    public const DAM_PODCAST_UI = 'dam_podcast_ui';

    // Podcast Episode
    public const DAM_PODCAST_EPISODE_CREATE = 'dam_podcastEpisode_create';
    public const DAM_PODCAST_EPISODE_UPDATE = 'dam_podcastEpisode_update';
    public const DAM_PODCAST_EPISODE_VIEW = 'dam_podcastEpisode_view';
    public const DAM_PODCAST_EPISODE_DELETE = 'dam_podcastEpisode_delete';
    public const DAM_PODCAST_EPISODE_UI = 'dam_podcastEpisode_ui';

    // Distribution Category
    public const DAM_DISTRIBUTION_CATEGORY_CREATE = 'dam_distributionCategory_create';
    public const DAM_DISTRIBUTION_CATEGORY_UPDATE = 'dam_distributionCategory_update';
    public const DAM_DISTRIBUTION_CATEGORY_VIEW = 'dam_distributionCategory_view';
    public const DAM_DISTRIBUTION_CATEGORY_DELETE = 'dam_distributionCategory_delete';
    public const DAM_DISTRIBUTION_CATEGORY_UI = 'dam_distributionCategory_ui';

    // Distribution Category Select
    public const DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE = 'dam_distributionCategorySelect_update';
    public const DAM_DISTRIBUTION_CATEGORY_SELECT_VIEW = 'dam_distributionCategorySelect_view';
    public const DAM_DISTRIBUTION_CATEGORY_SELECT_UI = 'dam_distributionCategorySelect_ui';

    // Distribution
    public const DAM_DISTRIBUTION_ACCESS = 'dam_distribution_access';
    public const DAM_DISTRIBUTION_VIEW = 'dam_distribution_view';

    // Asset External Provider
    public const DAM_ASSET_EXTERNAL_PROVIDER_ACCESS = 'dam_assetExternalProvider_access';

    // PermissionGroup
    public const DAM_PERMISSION_GROUP_CREATE = 'dam_permissionGroup_create';
    public const DAM_PERMISSION_GROUP_UPDATE = 'dam_permissionGroup_update';
    public const DAM_PERMISSION_GROUP_VIEW = 'dam_permissionGroup_view';
    public const DAM_PERMISSION_GROUP_DELETE = 'dam_permissionGroup_delete';
    public const DAM_PERMISSION_GROUP_UI = 'dam_permissionGroup_ui';

    // Log
    public const DAM_LOG_UI = 'dam_log_ui';

    // VideoShow
    public const DAM_VIDEO_SHOW_CREATE = 'dam_videoShow_create';
    public const DAM_VIDEO_SHOW_UPDATE = 'dam_videoShow_update';
    public const DAM_VIDEO_SHOW_VIEW = 'dam_videoShow_view';
    public const DAM_VIDEO_SHOW_DELETE = 'dam_videoShow_delete';
    public const DAM_VIDEO_SHOW_UI = 'dam_videoShow_ui';

    // VideoShowEpisode
    public const DAM_VIDEO_SHOW_EPISODE_CREATE = 'dam_videoShowEpisode_create';
    public const DAM_VIDEO_SHOW_EPISODE_UPDATE = 'dam_videoShowEpisode_update';
    public const DAM_VIDEO_SHOW_EPISODE_VIEW = 'dam_videoShowEpisode_view';
    public const DAM_VIDEO_SHOW_EPISODE_DELETE = 'dam_videoShowEpisode_delete';
    public const DAM_VIDEO_SHOW_EPISODE_UI = 'dam_videoShowEpisode_ui';

    // Job
    public const DAM_JOB_VIEW = 'dam_job_view';
    public const DAM_JOB_CREATE = 'dam_job_create';
    public const DAM_JOB_DELETE = 'dam_job_delete';
    public const DAM_JOB_UI = 'dam_job_ui';

    public const ALL = [
        self::DAM_ASSET_CREATE,
        self::DAM_ASSET_UPDATE,
        self::DAM_ASSET_VIEW,
        self::DAM_ASSET_DELETE,
        self::DAM_VIDEO_CREATE,
        self::DAM_VIDEO_UPDATE,
        self::DAM_VIDEO_VIEW,
        self::DAM_VIDEO_DELETE,
        self::DAM_AUDIO_CREATE,
        self::DAM_AUDIO_UPDATE,
        self::DAM_AUDIO_VIEW,
        self::DAM_AUDIO_DELETE,
        self::DAM_CUSTOM_FORM_CREATE,
        self::DAM_CUSTOM_FORM_UPDATE,
        self::DAM_CUSTOM_FORM_VIEW,
        self::DAM_CUSTOM_FORM_ELEMENT_VIEW,
        self::DAM_DOCUMENT_CREATE,
        self::DAM_DOCUMENT_UPDATE,
        self::DAM_DOCUMENT_VIEW,
        self::DAM_DOCUMENT_DELETE,
        self::DAM_IMAGE_CREATE,
        self::DAM_IMAGE_UPDATE,
        self::DAM_IMAGE_VIEW,
        self::DAM_IMAGE_DELETE,
        self::DAM_REGION_OF_INTEREST_CREATE,
        self::DAM_REGION_OF_INTEREST_UPDATE,
        self::DAM_REGION_OF_INTEREST_VIEW,
        self::DAM_REGION_OF_INTEREST_DELETE,
        self::DAM_EXT_SYSTEM_UPDATE,
        self::DAM_EXT_SYSTEM_VIEW,
        self::DAM_EXT_SYSTEM_LIST,
        self::DAM_EXT_SYSTEM_UI,
        self::DAM_ASSET_LICENCE_CREATE,
        self::DAM_ASSET_LICENCE_UPDATE,
        self::DAM_ASSET_LICENCE_VIEW,
        self::DAM_ASSET_LICENCE_LIST,
        self::DAM_ASSET_LICENCE_UI,
        self::DAM_AUTHOR_CREATE,
        self::DAM_AUTHOR_UPDATE,
        self::DAM_AUTHOR_VIEW,
        self::DAM_AUTHOR_DELETE,
        self::DAM_AUTHOR_UI,
        self::DAM_KEYWORD_CREATE,
        self::DAM_KEYWORD_UPDATE,
        self::DAM_KEYWORD_VIEW,
        self::DAM_KEYWORD_DELETE,
        self::DAM_KEYWORD_UI,
        self::DAM_DISTRIBUTION_CATEGORY_CREATE,
        self::DAM_DISTRIBUTION_CATEGORY_UPDATE,
        self::DAM_DISTRIBUTION_CATEGORY_VIEW,
        self::DAM_DISTRIBUTION_CATEGORY_DELETE,
        self::DAM_DISTRIBUTION_CATEGORY_UI,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_VIEW,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_UI,
        self::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS,
        self::DAM_DISTRIBUTION_ACCESS,
        self::DAM_PODCAST_CREATE,
        self::DAM_PODCAST_UPDATE,
        self::DAM_PODCAST_VIEW,
        self::DAM_PODCAST_DELETE,
        self::DAM_PODCAST_UI,
        self::DAM_PODCAST_EPISODE_CREATE,
        self::DAM_PODCAST_EPISODE_UPDATE,
        self::DAM_PODCAST_EPISODE_VIEW,
        self::DAM_PODCAST_EPISODE_DELETE,
        self::DAM_PODCAST_EPISODE_UI,
        self::DAM_PERMISSION_GROUP_VIEW,
        self::DAM_PERMISSION_GROUP_CREATE,
        self::DAM_PERMISSION_GROUP_UPDATE,
        self::DAM_PERMISSION_GROUP_DELETE,
        self::DAM_PERMISSION_GROUP_UI,
        self::DAM_LOG_UI,
        self::DAM_VIDEO_SHOW_CREATE,
        self::DAM_VIDEO_SHOW_UPDATE,
        self::DAM_VIDEO_SHOW_VIEW,
        self::DAM_VIDEO_SHOW_DELETE,
        self::DAM_VIDEO_SHOW_UI,
        self::DAM_VIDEO_SHOW_EPISODE_CREATE,
        self::DAM_VIDEO_SHOW_EPISODE_UPDATE,
        self::DAM_VIDEO_SHOW_EPISODE_VIEW,
        self::DAM_VIDEO_SHOW_EPISODE_DELETE,
        self::DAM_VIDEO_SHOW_EPISODE_UI,
        self::DAM_JOB_VIEW,
        self::DAM_JOB_CREATE,
        self::DAM_JOB_DELETE,
        self::DAM_JOB_UI,
    ];

    public static function default(int $defaultGrant = Grant::DENY): array
    {
        $resolved = [];
        foreach (static::ALL as $permission) {
            $resolved[$permission] = $defaultGrant;
        }

        return $resolved;
    }
}
