<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Permission;

use AnzuSystems\Contracts\Security\Grant;

class DamPermissions
{
    // Asset
    public const string DAM_ASSET_CREATE = 'dam_asset_create';
    public const string DAM_ASSET_UPDATE = 'dam_asset_update';
    public const string DAM_ASSET_READ = 'dam_asset_read';
    public const string DAM_ASSET_DELETE = 'dam_asset_delete';

    // Video
    public const string DAM_VIDEO_CREATE = 'dam_video_create';
    public const string DAM_VIDEO_UPDATE = 'dam_video_update';
    public const string DAM_VIDEO_READ = 'dam_video_read';
    public const string DAM_VIDEO_DELETE = 'dam_video_delete';

    // Audio
    public const string DAM_AUDIO_CREATE = 'dam_audio_create';
    public const string DAM_AUDIO_UPDATE = 'dam_audio_update';
    public const string DAM_AUDIO_READ = 'dam_audio_read';
    public const string DAM_AUDIO_DELETE = 'dam_audio_delete';

    // Asset Custom Form
    public const string DAM_CUSTOM_FORM_CREATE = 'dam_assetCustomForm_create';
    public const string DAM_CUSTOM_FORM_UPDATE = 'dam_assetCustomForm_update';
    public const string DAM_CUSTOM_FORM_READ = 'dam_assetCustomForm_read';

    // Custom Form Element
    public const string DAM_CUSTOM_FORM_ELEMENT_VIEW = 'dam_customFormElement_read';

    // Document
    public const string DAM_DOCUMENT_CREATE = 'dam_document_create';
    public const string DAM_DOCUMENT_UPDATE = 'dam_document_update';
    public const string DAM_DOCUMENT_READ = 'dam_document_read';
    public const string DAM_DOCUMENT_DELETE = 'dam_document_delete';

    // Image
    public const string DAM_IMAGE_CREATE = 'dam_image_create';
    public const string DAM_IMAGE_UPDATE = 'dam_image_update';
    public const string DAM_IMAGE_READ = 'dam_image_read';
    public const string DAM_IMAGE_DELETE = 'dam_image_delete';

    // Region of Interest
    public const string DAM_REGION_OF_INTEREST_CREATE = 'dam_regionOfInterest_create';
    public const string DAM_REGION_OF_INTEREST_UPDATE = 'dam_regionOfInterest_update';
    public const string DAM_REGION_OF_INTEREST_READ = 'dam_regionOfInterest_read';
    public const string DAM_REGION_OF_INTEREST_DELETE = 'dam_regionOfInterest_delete';

    // ExtSystem
    public const string DAM_EXT_SYSTEM_UPDATE = 'dam_extSystem_update';
    public const string DAM_EXT_SYSTEM_READ = 'dam_extSystem_read';
    public const string DAM_EXT_SYSTEM_LIST = 'dam_extSystem_list';
    public const string DAM_EXT_SYSTEM_UI = 'dam_extSystem_ui';

    // AssetLicence
    public const string DAM_ASSET_LICENCE_CREATE = 'dam_assetLicence_create';
    public const string DAM_ASSET_LICENCE_UPDATE = 'dam_assetLicence_update';
    public const string DAM_ASSET_LICENCE_READ = 'dam_assetLicence_read';
    public const string DAM_ASSET_LICENCE_LIST = 'dam_assetLicence_list';
    public const string DAM_ASSET_LICENCE_UI = 'dam_assetLicence_ui';

    // AssetLicenceGroup
    public const string DAM_ASSET_LICENCE_GROUP_CREATE = 'dam_assetLicenceGroup_create';
    public const string DAM_ASSET_LICENCE_GROUP_UPDATE = 'dam_assetLicenceGroup_update';
    public const string DAM_ASSET_LICENCE_GROUP_READ = 'dam_assetLicenceGroup_read';
    public const string DAM_ASSET_LICENCE_GROUP_LIST = 'dam_assetLicenceGroup_list';
    public const string DAM_ASSET_LICENCE_GROUP_UI = 'dam_assetLicenceGroup_ui';

    // Author
    public const string DAM_AUTHOR_CREATE = 'dam_author_create';
    public const string DAM_AUTHOR_UPDATE = 'dam_author_update';
    public const string DAM_AUTHOR_READ = 'dam_author_read';
    public const string DAM_AUTHOR_DELETE = 'dam_author_delete';
    public const string DAM_AUTHOR_UI = 'dam_author_ui';

    // Keyword
    public const string DAM_KEYWORD_CREATE = 'dam_keyword_create';
    public const string DAM_KEYWORD_UPDATE = 'dam_keyword_update';
    public const string DAM_KEYWORD_READ = 'dam_keyword_read';
    public const string DAM_KEYWORD_DELETE = 'dam_keyword_delete';
    public const string DAM_KEYWORD_UI = 'dam_keyword_ui';

    // Podcast
    public const string DAM_PODCAST_CREATE = 'dam_podcast_create';
    public const string DAM_PODCAST_UPDATE = 'dam_podcast_update';
    public const string DAM_PODCAST_READ = 'dam_podcast_read';
    public const string DAM_PODCAST_DELETE = 'dam_podcast_delete';
    public const string DAM_PODCAST_UI = 'dam_podcast_ui';

    // Podcast Episode
    public const string DAM_PODCAST_EPISODE_CREATE = 'dam_podcastEpisode_create';
    public const string DAM_PODCAST_EPISODE_UPDATE = 'dam_podcastEpisode_update';
    public const string DAM_PODCAST_EPISODE_READ = 'dam_podcastEpisode_read';
    public const string DAM_PODCAST_EPISODE_DELETE = 'dam_podcastEpisode_delete';
    public const string DAM_PODCAST_EPISODE_UI = 'dam_podcastEpisode_ui';

    // Distribution Category
    public const string DAM_DISTRIBUTION_CATEGORY_CREATE = 'dam_distributionCategory_create';
    public const string DAM_DISTRIBUTION_CATEGORY_UPDATE = 'dam_distributionCategory_update';
    public const string DAM_DISTRIBUTION_CATEGORY_READ = 'dam_distributionCategory_read';
    public const string DAM_DISTRIBUTION_CATEGORY_DELETE = 'dam_distributionCategory_delete';
    public const string DAM_DISTRIBUTION_CATEGORY_UI = 'dam_distributionCategory_ui';

    // Distribution Category Select
    public const string DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE = 'dam_distributionCategorySelect_update';
    public const string DAM_DISTRIBUTION_CATEGORY_SELECT_READ = 'dam_distributionCategorySelect_read';
    public const string DAM_DISTRIBUTION_CATEGORY_SELECT_UI = 'dam_distributionCategorySelect_ui';

    // Distribution
    public const string DAM_DISTRIBUTION_ACCESS = 'dam_distribution_access';
    public const string DAM_DISTRIBUTION_VIEW = 'dam_distribution_read';

    // Asset External Provider
    public const string DAM_ASSET_EXTERNAL_PROVIDER_ACCESS = 'dam_assetExternalProvider_access';

    // PermissionGroup
    public const string DAM_PERMISSION_GROUP_CREATE = 'dam_permissionGroup_create';
    public const string DAM_PERMISSION_GROUP_UPDATE = 'dam_permissionGroup_update';
    public const string DAM_PERMISSION_GROUP_READ = 'dam_permissionGroup_read';
    public const string DAM_PERMISSION_GROUP_DELETE = 'dam_permissionGroup_delete';
    public const string DAM_PERMISSION_GROUP_UI = 'dam_permissionGroup_ui';

    // Log
    public const string DAM_LOG_UI = 'dam_log_ui';

    // VideoShow
    public const string DAM_VIDEO_SHOW_CREATE = 'dam_videoShow_create';
    public const string DAM_VIDEO_SHOW_UPDATE = 'dam_videoShow_update';
    public const string DAM_VIDEO_SHOW_READ = 'dam_videoShow_read';
    public const string DAM_VIDEO_SHOW_DELETE = 'dam_videoShow_delete';
    public const string DAM_VIDEO_SHOW_UI = 'dam_videoShow_ui';

    // VideoShowEpisode
    public const string DAM_VIDEO_SHOW_EPISODE_CREATE = 'dam_videoShowEpisode_create';
    public const string DAM_VIDEO_SHOW_EPISODE_UPDATE = 'dam_videoShowEpisode_update';
    public const string DAM_VIDEO_SHOW_EPISODE_READ = 'dam_videoShowEpisode_read';
    public const string DAM_VIDEO_SHOW_EPISODE_DELETE = 'dam_videoShowEpisode_delete';
    public const string DAM_VIDEO_SHOW_EPISODE_UI = 'dam_videoShowEpisode_ui';

    // Job
    public const string DAM_JOB_VIEW = 'dam_job_read';
    public const string DAM_JOB_CREATE = 'dam_job_create';
    public const string DAM_JOB_DELETE = 'dam_job_delete';
    public const string DAM_JOB_UI = 'dam_job_ui';

    // Job
    public const string DAM_AUTHOR_CLEAN_PHRASE_READ = 'dam_authorCleanPhrase_read';
    public const string DAM_AUTHOR_CLEAN_PHRASE_CREATE = 'dam_authorCleanPhrase_create';
    public const string DAM_AUTHOR_CLEAN_PHRASE_DELETE = 'dam_authorCleanPhrase_delete';
    public const string DAM_AUTHOR_CLEAN_PHRASE_UPDATE = 'dam_authorCleanPhrase_update';
    public const string DAM_AUTHOR_CLEAN_PHRASE_UI = 'dam_authorCleanPhrase_ui';

    public const array ALL = [
        self::DAM_ASSET_CREATE,
        self::DAM_ASSET_UPDATE,
        self::DAM_ASSET_READ,
        self::DAM_ASSET_DELETE,
        self::DAM_VIDEO_CREATE,
        self::DAM_VIDEO_UPDATE,
        self::DAM_VIDEO_READ,
        self::DAM_VIDEO_DELETE,
        self::DAM_AUDIO_CREATE,
        self::DAM_AUDIO_UPDATE,
        self::DAM_AUDIO_READ,
        self::DAM_AUDIO_DELETE,
        self::DAM_CUSTOM_FORM_CREATE,
        self::DAM_CUSTOM_FORM_UPDATE,
        self::DAM_CUSTOM_FORM_READ,
        self::DAM_CUSTOM_FORM_ELEMENT_VIEW,
        self::DAM_DOCUMENT_CREATE,
        self::DAM_DOCUMENT_UPDATE,
        self::DAM_DOCUMENT_READ,
        self::DAM_DOCUMENT_DELETE,
        self::DAM_IMAGE_CREATE,
        self::DAM_IMAGE_UPDATE,
        self::DAM_IMAGE_READ,
        self::DAM_IMAGE_DELETE,
        self::DAM_REGION_OF_INTEREST_CREATE,
        self::DAM_REGION_OF_INTEREST_UPDATE,
        self::DAM_REGION_OF_INTEREST_READ,
        self::DAM_REGION_OF_INTEREST_DELETE,
        self::DAM_EXT_SYSTEM_UPDATE,
        self::DAM_EXT_SYSTEM_READ,
        self::DAM_EXT_SYSTEM_LIST,
        self::DAM_EXT_SYSTEM_UI,
        self::DAM_ASSET_LICENCE_CREATE,
        self::DAM_ASSET_LICENCE_UPDATE,
        self::DAM_ASSET_LICENCE_READ,
        self::DAM_ASSET_LICENCE_LIST,
        self::DAM_ASSET_LICENCE_UI,
        self::DAM_AUTHOR_CREATE,
        self::DAM_AUTHOR_UPDATE,
        self::DAM_AUTHOR_READ,
        self::DAM_AUTHOR_DELETE,
        self::DAM_AUTHOR_UI,
        self::DAM_KEYWORD_CREATE,
        self::DAM_KEYWORD_UPDATE,
        self::DAM_KEYWORD_READ,
        self::DAM_KEYWORD_DELETE,
        self::DAM_KEYWORD_UI,
        self::DAM_DISTRIBUTION_CATEGORY_CREATE,
        self::DAM_DISTRIBUTION_CATEGORY_UPDATE,
        self::DAM_DISTRIBUTION_CATEGORY_READ,
        self::DAM_DISTRIBUTION_CATEGORY_DELETE,
        self::DAM_DISTRIBUTION_CATEGORY_UI,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_READ,
        self::DAM_DISTRIBUTION_CATEGORY_SELECT_UI,
        self::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS,
        self::DAM_DISTRIBUTION_ACCESS,
        self::DAM_PODCAST_CREATE,
        self::DAM_PODCAST_UPDATE,
        self::DAM_PODCAST_READ,
        self::DAM_PODCAST_DELETE,
        self::DAM_PODCAST_UI,
        self::DAM_PODCAST_EPISODE_CREATE,
        self::DAM_PODCAST_EPISODE_UPDATE,
        self::DAM_PODCAST_EPISODE_READ,
        self::DAM_PODCAST_EPISODE_DELETE,
        self::DAM_PODCAST_EPISODE_UI,
        self::DAM_PERMISSION_GROUP_READ,
        self::DAM_PERMISSION_GROUP_CREATE,
        self::DAM_PERMISSION_GROUP_UPDATE,
        self::DAM_PERMISSION_GROUP_DELETE,
        self::DAM_PERMISSION_GROUP_UI,
        self::DAM_LOG_UI,
        self::DAM_VIDEO_SHOW_CREATE,
        self::DAM_VIDEO_SHOW_UPDATE,
        self::DAM_VIDEO_SHOW_READ,
        self::DAM_VIDEO_SHOW_DELETE,
        self::DAM_VIDEO_SHOW_UI,
        self::DAM_VIDEO_SHOW_EPISODE_CREATE,
        self::DAM_VIDEO_SHOW_EPISODE_UPDATE,
        self::DAM_VIDEO_SHOW_EPISODE_READ,
        self::DAM_VIDEO_SHOW_EPISODE_DELETE,
        self::DAM_VIDEO_SHOW_EPISODE_UI,
        self::DAM_JOB_VIEW,
        self::DAM_JOB_CREATE,
        self::DAM_JOB_DELETE,
        self::DAM_JOB_UI,
        self::DAM_DISTRIBUTION_VIEW,
        self::DAM_DISTRIBUTION_ACCESS,
        self::DAM_ASSET_LICENCE_GROUP_READ,
        self::DAM_ASSET_LICENCE_GROUP_UPDATE,
        self::DAM_ASSET_LICENCE_GROUP_LIST,
        self::DAM_ASSET_LICENCE_GROUP_CREATE,
        self::DAM_ASSET_LICENCE_GROUP_UI,
        self::DAM_AUTHOR_CLEAN_PHRASE_READ,
        self::DAM_AUTHOR_CLEAN_PHRASE_CREATE,
        self::DAM_AUTHOR_CLEAN_PHRASE_UPDATE,
        self::DAM_AUTHOR_CLEAN_PHRASE_DELETE,
        self::DAM_AUTHOR_CLEAN_PHRASE_UI,
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
