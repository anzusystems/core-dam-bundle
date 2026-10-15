# Changelog

## Unreleased

### Features
* Add sys endpoint `POST /api/sys/v1/video/from-url` (`VideoFileSysUrlCreateDto`): asynchronous download of a video by URL with dedup on URL + licence also over files still `Uploaded`/`Stored` (the existing file is returned unchanged, a hit still in `Uploaded` or `Stored` dispatches nothing, the original message is in flight), `distributionServices` unique and limited to `jwDistribution` services of the ext system, mandatory `customData.title`, `customData.description` and a non-blank author when distributing (a 422 reports the text errors on the paths `customData.title` / `customData.description`), optional `imagePreviewAssetFileId` (processed image of the same licence; any other id is ignored and the video gets the generated thumbnail); no public route is generated for video; on a dedup hit with a `Processed` file the requested `distributionServices` are ensured for the existing video; `customData.title`/`description` are cut to `JwTexts::TITLE_LENGTH`/`DESCRIPTION_LENGTH` when the JW distribution payload is built, instead of being rejected
* Add `VideoFileChangeStateMessage::$distributionServices` (default `[]`, so queued messages from before the deploy stay valid): after the video is `Processed`, `VideoDownloadFacade::finishProcessing()` (called by `AssetFileChangeStateHandler`) ensures a `JwDistribution` for each service through `VideoDistributionFacade::ensureDistributed()` (idempotent, never restarts an existing distribution, runs without a logged user so `createdBy` is the anonymous user)
* Add DAM to ext system media status callbacks `videoFailed` (download/processing failed, reason is the `AssetFileFailedType` value, or `distribution_failed`, sent when the JW distribution fails before the video reached JW (no `extId`) or when the distribution request is rejected by validation, e.g. the origin of a duplicate has no author; a later failure of an already distributed video sends nothing) and `videoDuplicate` (checksum duplicate; the origin video file is distributed) on `POST …/dam/media/status`; the payload always contains `originAssetId` (the origin asset) and `originAssetFileId` (the origin file), both `null` unless the status is `videoDuplicate`; the callbacks are sent only for videos created by `POST /api/sys/v1/video/from-url` (create strategy `download`). `ExtSystemCallbackInterface::notifyMediaStatus()` and `ExtSystemCallbackFacade::notifyMediaStatus()` gain the optional `$originAssetId` and `$originAssetFileId` parameters
* **BC break**: `ExtSystemCallbackInterface::notifyMediaStatus()` gains the `$originAssetId` and `$originAssetFileId` parameters — every implementor must add both
* `AssetFileChangeStateHandler` takes a non-blocking lock per asset file for 30 minutes (`ResourceLocker`, requires `anzusystems/common-bundle` `^11.8` for the `ttl` argument): a message redelivered after the Pub/Sub ack deadline while the file is still being downloaded or processed fails and is retried later instead of processing the same file twice
* **BC break**: `TtsMediaStatusCallbackMessage` is renamed to `MediaStatusCallbackMessage` (and `TtsMediaStatusCallbackHandler` to `MediaStatusCallbackHandler`) and gains `$originAssetId` and `$originAssetFileId` — hosts that route the message in `messenger.php` must update the class name; the transport is named `anzu_core_dam_media_api_callback`; before the deploy check that no `TtsMediaStatusCallbackMessage` is queued on the transport `anzu_core_dam_media_api_callback` (wait until TTS callbacks drain; do not purge the transport, it also carries `MediaApiCallbackMessage`), because a queued one cannot be decoded after the rename; during a rolling update a new message read by an old worker is rejected and redelivered, so it heals itself
* Add `AbstractAssetFileRepository::findOneInProgressOrProcessedByUrlAndLicence()` (statuses `Uploaded`, `Stored`, `Processed`), used for the dedup of `POST /api/sys/v1/video/from-url`
* `AssetFileChangeStateHandler` gains `VideoDownloadFacade` as a constructor dependency; add `MediaStatusCallbackMessage::fromVideoFile()`, `AssetFile::isCreatedByDownload()`, `MediaStatusType` implements `EnumInterface`
* Add `JwTexts::TITLE_LENGTH` and `JwTexts::DESCRIPTION_LENGTH`; `VideoFileSysUrlCreateValidator` no longer rejects over-long title/description
* `VideoStatusFacade` keeps an already set image preview and generates the ffmpeg thumbnail only when the video has none
* **BC break** (PHP extensions only, the JSON contract of `POST /api/sys/v1/asset-file` and `/asset-file/from-url` is unchanged): `generatePublicRoute` moved from `AbstractAssetFileSysDto` to `AssetFileSysPathCreateDto` and `AssetFileSysUrlCreateDto`; `AssetFileSysUrlCreateDto` now extends the new `AbstractAssetFileSysUrlCreateDto` (`url`, `authors`, `keywords`); `AssetSysFacade::setupKeywords()`/`setupAuthors()` take `AbstractAssetFileSysUrlCreateDto`; `AssetSysFacade::createFromDto()` no longer generates the public route, the create methods do
* Add sys endpoint `POST /api/sys/v1/image/first-use` to write-once set `AssetFile::$firstUsedAt` — hosts need a migration for the new nullable `asset_file.first_used_at` column; the column ships unmapped until the host migration lands; partial-success semantics: unknown damIds and items in licences outside the caller's scope are skipped (skips logged as warning), never a 4xx
* Add `JobAuthorNameReindex` job, automatically enqueued when an author is renamed via the adm update endpoint — hosts need a migration for the new `job_author_name_reindex` table (JOINED `Job` inheritance)
* Add Elasticsearch asset mapping fields `authorNames` (fulltext, boosted) and `uploadedAt` (`epoch_second`, exposed as adm range filter `uploadedAtFrom`/`uploadedAtUntil`) — a full reindex is required to populate existing documents
* Add `exif_metadata.iptc_fallback_charset` config option (default `null`, preserves previous behaviour): when set, undeclared IPTC values are auto-detected as UTF-8 per value, falling back to the configured charset only when the value isn't valid UTF-8
* Add `CollectionHelper::groupBy`
* Add `AssetLicence` embeds `flags` (`manualUploadAllowed`, `directUseAllowed`, both default `true`) and `autoDelete` (`active` default `false`, `olderThanDays` validated `> 1` when active), editable via the adm licence API — hosts need a migration for the four new `asset_licence` columns (all with DB defaults, old code keeps running during deploy)
* Add manual upload guard: licences with `flags.manualUploadAllowed=false` reject adm file create, create-to-asset, external provider upload and adm copy target with `licence_manual_upload_disabled`; sys API, commands and job processors stay unguarded by design
* Add `asset_licence_storage_overrides` bundle config (immutable map licence id => `storage_name`/`crop_storage_name`): `FileSystemProvider` resolves asset files, optimal resizes and crop cache through the override (`AssetLicenceStorageOverrideProvider`), `AssetFileDeleteEvent` carries the crop storage name resolved while the entity is alive
* Add `AssetLicenceRetentionFacade::deleteExpiredAssets()` — batch sweep deleting assets older than the licence `autoDelete` threshold (conditions enforced in SQL, licence config re-read every batch so disabling stops a running sweep, usage is deliberately not checked because retention licences are not directly usable, undeletable assets skipped with a warning); hosts wire it into a daily cron command with Sentry check-ins
* **BC break**: raise minimum PHP version to `>=8.5`; base Docker image updated to `anzusystems/php:5.1.0-php85-cli-vipsffmpeg`
* Add Symfony `^8.0` support alongside `^7.4` (dual compatibility on all `symfony/*` packages); `petitpress/gps-messenger-bundle` allowed at `^3.2 || ^4.0` and `symfony/monolog-bundle` at `^3.11 || ^4.0` for Symfony 8 compatibility
* **BC break**: `ExtSystemCallbackInterface` gains a required `isImageFileUsedBulk()` method — every implementor must add it
* **BC break**: `AssetFileManager::canBeRemoved()` is now `final` — override `canBeRemovedBulk()` instead
* Usage checks on delete are now fail-closed (an unavailable or erroring callback is treated as used); the usage check is now also enforced in `AssetFacade::delete()`/`deleteBulk()`; `deleteUnfinishedUploads` skips assets in use instead of failing
* `AssetFileCopyBuilder` carries `flags.singleUse` and records `originAssetId` (source file id) on copies; `originAssetId` is now serialized
* `AssetSysFactory::createFromDto()` accepts an optional service-level `$sourceStorageName` — storage override was removed from the sys DTO wire format
* Undeclared-IPTC charset recovery is scoped to tags actually read from the IPTC group (extra lightweight exiftool read per file when `iptc_fallback_charset` is set) — EXIF/XMP values can no longer be reinterpreted; `ExifTagNormalizer::normalizeTags()` accepts optional `$iptcTagNames` (null keeps the apply-to-all behaviour for callers without group info)
* **BC break** (manual construction only, DI unaffected): `Exiftool` requires `ExifTagNormalizer`, `AssetMetadataProcessor` requires `ExifMetadataFilter` and `AssetQueryFactory` requires `AssetFulltextQueryBuilderInterface` as constructor dependencies — the `?Service = null` fallbacks were removed
* Rename container bind `$searcNext` to `$searchNext` (typo); env `ELASTICSEARCH_NEXT_ENABLED` and behaviour unchanged
* `AssetFileDBALRepository::updateFirstUsedAtIfUnset()` writes `modified_at` from `App::getAppDate()` instead of SQL `NOW()`

### Fixes
* `FileFactory::createFromChunks()` copies each uploaded chunk into the temporary file as a stream instead of reading the whole chunk (up to `50 MB`) into a string, which lowers the peak memory of the file change state workers; **BC break** for direct callers: `LocalFilesystem::appendTmpStream()` / `LocalFileSystemAdapter::appendTmpStream()` now take a stream resource instead of a string
* Fix url asset file download refusing redirects for untrusted hosts (`1.48.0` regression) — podcast enclosures are redirect trackers, so the empty `302` body was stored as the audio file and the asset failed on `invalid_mime_type` (`application/x-empty`)
* Fail url asset file download on any non-`2xx` status instead of writing an empty file
* Fix TTS expired-audio cleanup failing the whole batch on an orphaned audio file (asset row gone): `AssetFileRouteGenerator::getFullUrl()` no longer reads the asset, and `TtsAudioFileRemover` continues after a failed file with a new EntityManager (`ManagerRegistry::resetManager()`, as Doctrine recommends after a failed transaction) so the rest of the batch is still removed
* Podcast full sync skips podcasts with an empty `rssUrl` instead of requesting an empty URL and logging an error on every run

## [1.40.0](https://github.com/anzusystems/core-dam-bundle/compare/1.39.0...1.40.0) (2025-09-22)

### Features
* Update PHP base image to `anzusystems/php:4.1.0-php83-cli-vipsffmpeg` with php `8.3.20`
* Add `DOCKER_COMPOSE_SERVICE_NAME` configuration support for flexible Docker service naming
* Update `anzusystems/common-bundle` to `^9.2` with mongo `2.*` support
* Update `petitpress/gps-messenger-bundle` to `^3.2`
* Add full support for `GOOGLE_PUBSUB_SA_KEY` environment variable needed by updated `petitpress/gps-messenger-bundle` package
* Standardize environment variable naming (`DB_CORE_DAM_BUNDLE_*` instead of `DB_COMMONBUNDLE_*`) for better consistency
* Fix job processor method signatures to return `bool` instead of `void` for better error handling
* Improve code quality with property visibility fixes and documentation updates

### Fixes
* Fix environment variable expansion in `bin/*` scripts (bash and build scripts)

## [1.39.0](https://github.com/anzusystems/core-dam-bundle/compare/1.38.0...1.39.0) (2025-09-01)

### Features
* Add ZIP mime type and refactor asset change detection logic
* Update asset change detection logic to include authors comparison

## [1.38.0](https://github.com/anzusystems/core-dam-bundle/compare/1.37.0...1.38.0) (2025-08-25)

### Features
* Introduce AssetMetadataBulkEventDispatcher to handle bulk metadata changes and integrate it into relevant facades and workflows
* Refactor transaction handling in AssetFileRouteFacade and PodcastEpisodeFacade to ensure rollback only if transaction is active

## [1.37.0](https://github.com/anzusystems/core-dam-bundle/compare/1.36.0...1.37.0) (2024-07-29)

### Features
* Add document support for ICC

## [1.36.0](https://github.com/anzusystems/core-dam-bundle/compare/1.35.0...1.36.0) (2024-07-22)

### Features
* Allow to define custom importFrom date when importing single podcast
* Allow to define podcastId in `src/Command/GeneratePodcastImportJobsCommand.php`

## [1.35.0](https://github.com/anzusystems/core-dam-bundle/compare/1.34.0...1.35.0) (2024-07-07)

### Features
* RSS URL management feature

## [1.34.0](https://github.com/anzusystems/core-dam-bundle/compare/1.33.0...1.34.0) (2024-05-15)

### Changes
* Removed second level cache from entities

## [1.33.0](https://github.com/anzusystems/core-dam-bundle/compare/1.32.0...1.33.0) (2024-05-12)

### Features
* anzusystems/common-bundle update
* Implemented `AuditLogResourceHelper`
* Implemented `getEnvironments` to `Fixtures`
* AssetFacade and AssetFileFacade callback `canBeRemoved`

## [1.32.0](https://github.com/anzusystems/core-dam-bundle/compare/1.31.2...1.32.0) (2024-04-17)

### Features
* SYS API for create JobImageCopy
* Callback system triggered after JobImageCopy is finished
* Expose enabled in ExtSystemAssetTypeAdmGetDecorator

## [1.31.2](https://github.com/anzusystems/core-dam-bundle/compare/1.31.1...1.31.2) (2024-03-31)

### Fixes
* Fixed `AssetLicenceAwareVoter` to work with null subject
* Fixed `AssetCopyEqualExtSystemValidator` for not found values

## [1.31.1](https://github.com/anzusystems/core-dam-bundle/compare/1.31.0...1.31.1) (2024-03-19)

### Features
* Added `AssetFileCopiedEvent` and new notification `asset_file_copied`

## [1.30.3](https://github.com/anzusystems/core-dam-bundle/compare/1.30.2...1.30.3) (2024-02-26)

### Fixes
* Reverted default sort in AbstractQueryFactory.php

## [1.30.2](https://github.com/anzusystems/core-dam-bundle/compare/1.30.1...1.30.2) (2024-02-25)

### Fixes
* Fixed PublicExport.php create, assign ExtSystem

## [1.30.1](https://github.com/anzusystems/core-dam-bundle/compare/1.30.0...1.30.1) (2024-02-24)

### Fixes
* Fix manage `PodcastEpisodeFlags`

## [1.30.0](https://github.com/anzusystems/core-dam-bundle/compare/1.29.0...1.30.0) (2024-02-07)

### Features
* Fixed podcast episode rss duration
* Default PodcastEpisodeDates value

## [1.29.0](https://github.com/anzusystems/core-dam-bundle/compare/1.28.0...1.29.0) (2024-02-04)

### Features
* Added image origin file suffix 'orig'
* Allow to disable crop cache
* Optimal resizes quality set to 90

## [1.28.0](https://github.com/anzusystems/core-dam-bundle/compare/1.27.0...1.28.0) (2024-02-03)

### Features
* Distribution management admin

## [1.27.0](https://github.com/anzusystems/core-dam-bundle/compare/1.26.2...1.27.0) (2024-01-30)

### Features
* API for update/remove Asset sibling
* Introduced `PublicExport` entity
* Added fields `attributes.mobileOrderPosition`, `attributes.webOrderPosition`, `flags.mobilePublicExportEnabled`, `flags.webPublicExportEnabled` to entities `Podcast`, `PodcastEpisode`, `VideoShow`, `VideoShowEpisode`
* Refactored `Distribution` custom data factories
* `JwDistribution` provides `directSourceUrl`
* Added indexes to entities `Podcast`, `PodcastEpisode`, `VideoShow`, `VideoShowEpisode`
* Author `currentAuthors` manage
* Introduced `EntityIterator` service
* Elasticsearch:
  * Asset search by customDataKey and customDataValue
  * Asset textSearch introduced priority boosters
* ImageLinksHandler - allow to export also public links

## [1.26.2](https://github.com/anzusystems/core-dam-bundle/compare/1.26.1...1.26.2) (2024-01-14)

### Fixes
* Fixed setting setLastProcessedId in index builder

## [1.26.1](https://github.com/anzusystems/core-dam-bundle/compare/1.26.0...1.26.1) (2024-01-13)

### Fixes
* Elastic rebuild fixes most dominant color and specific fields refactor

## [1.26.0](https://github.com/anzusystems/core-dam-bundle/compare/1.25.0...1.26.0) (2024-01-13)

### Features
* Add support for DBAL elastic reindex
* Asset now reindex using DBALIndexable
* Distribution add extSystemId and fixed Distribution elastic reindex

## [1.25.0](https://github.com/anzusystems/core-dam-bundle/compare/1.24.0...1.25.0) (2024-01-08)

### Features
* Changed JW thumbnail upload method from download from URL to direct upload

## [1.24.0](https://github.com/anzusystems/core-dam-bundle/compare/1.23.0...1.24.0) (2024-01-03)

### Features
* Allow to set JWplayer video thumbnail in Video distribution

## [1.23.0](https://github.com/anzusystems/core-dam-bundle/compare/1.22.0...1.23.0) (2024-01-03)

### Features
* Added IDX_licence_attributes_status_url to asset_file

## [1.22.0](https://github.com/anzusystems/core-dam-bundle/compare/1.21.0...1.22.0) (2024-12-20)

### Features
* API for bulk metadata update. Now does not clean undefined fields

## [1.21.0](https://github.com/anzusystems/core-dam-bundle/compare/1.20.2...1.21.0) (2024-12-16)

### Features
* Introduced a new AuthorCleanPhrase entity that allows you to store phrases used to process Author exif data. This should result in better Author matching in the AssetMetadataProcess phase of asset upload

## [1.20.2](https://github.com/anzusystems/core-dam-bundle/compare/1.20.1...1.20.2) (2024-12-05)

### Features
* AssetMetadataProcessor uses `htmlspecialchars` with flag `ENT_SUBSTITUTE` for parsing values

## [1.20.1](https://github.com/anzusystems/core-dam-bundle/compare/1.20.0...1.20.1) (2024-12-02)

### Features
* Added optimize author validation
* Handle not found author in job processor correctly

## [1.20.0](https://github.com/anzusystems/core-dam-bundle/compare/1.19.0...1.20.0) (2024-12-02)

### Features
* Added relation between the author and the current author
* Added logic for assigning the current author
* Job for processing current author

## [1.19.0](https://github.com/anzusystems/core-dam-bundle/compare/1.18.0...1.19.0) (2024-11-26)

### Features
* JobImageCopyItem changed FK relations to simple strings, this fixes DELETE asset problem
* JobImageCopy now also copies tracking fields
* Increase `MAX_ASSETS_PER_JOB` in `GenerateCopyJobCommand` and renamed constants

## [1.18.0](https://github.com/anzusystems/core-dam-bundle/compare/1.17.0...1.18.0) (2024-11-19)

### Features
* Fixed KW duplicate check on create
* JOB for ImageOptimalResizeReprocess

## [1.17.0](https://github.com/anzusystems/core-dam-bundle/compare/1.16.0...1.17.0) (2024-11-19)

### Features
* Fixed Author and Keyword suggester problem with validation requirements
* Job for image copy
* Clean crop cache updates
* KW create API checks, if exists

## [1.16.0](https://github.com/anzusystems/core-dam-bundle/compare/1.15.0...1.16.0) (2024-11-04)

### Features
* Default Author order by reviewed + score
* PNG resizes and crop served as png
* KW changed collation
* Crop cache updates
* Add filesystem provider method
* Disable vips cache (memory problems fix)

## [1.15.0](https://github.com/anzusystems/core-dam-bundle/compare/1.14.0...1.15.0) (2024-10-23)

### Features
* Single use edit support

## [1.14.0](https://github.com/anzusystems/core-dam-bundle/compare/1.13.0...1.14.0) (2024-10-23)

### Features
* Image copy licence API
* Search by Image public URL

## [1.13.0](https://github.com/anzusystems/core-dam-bundle/compare/1.12.0...1.13.0) (2024-10-14)

### Features
* Set Asset status to `with_file` immediate after assetFile process

## [1.12.0](https://github.com/anzusystems/core-dam-bundle/compare/1.11.0...1.12.0) (2024-10-07)

### Features
* Fixed resourceLocker in Asset processing that causes duplicity bypass
* Removed htmlentities encoding from exif metadata processing
* Fixed Podcast import set processing status in Processor
* Podcast importer import from modifier
* Elasticsearch Asset:
  * Search by author
  * Search by user created
  * Search by assetId or mainFileId
* Improved tests

## [1.11.0](https://github.com/anzusystems/core-dam-bundle/compare/1.10.0...1.11.0) (2024-07-08)

### Changes
* php83 -> AddTypeToConstRector
* doctrine 2 -> 3 update
* symfony 6.4 -> 7 update
* User ACL update, renamed _VIEW to _READ

## [1.10.0](https://github.com/anzusystems/core-dam-bundle/compare/1.9.1...1.10.0) (2024-06-04)

### Features
* Audio application/octet-stream mime fix
* LicenceGroupCreate fix
* Add log message for download asset fail
* Increased asset download time configuration

## [1.9.1](https://github.com/anzusystems/core-dam-bundle/compare/1.9.0...1.9.1) (2024-05-09)

### Fixes
* update licence collection in `AssetLicenceGroup` update

## [1.9.0](https://github.com/anzusystems/core-dam-bundle/compare/1.8.2...1.9.0) (2024-04-25)

### Features
* package updates
* SYS API -> create image from url (with authors, keywords, ...)

## [1.8.2](https://github.com/anzusystems/core-dam-bundle/compare/1.8.1...1.8.2) (2024-03-12)

### Features
* Podcast episode extUrl, podcast extUrl
* 'avif' support

## [1.8.1](https://github.com/anzusystems/core-dam-bundle/compare/1.8.0...1.8.1) (2024-02-29)
### Changes
* Podcast rss url length validation
* `AudioFileAdmListDto` added imagePreview

## [1.8.0](https://github.com/anzusystems/core-dam-bundle/compare/1.7.0...1.8.0) (2024-02-27)
### Changes
* New elasticsearch index `distribution`
* Allow search asset by multiple licences
* Support for `LicenceGroups`
  * updated voters

## [1.7.0](https://github.com/anzusystems/core-dam-bundle/compare/1.6.1...1.7.0) (2024-01-18)
### Changes
* `FileFactory` clears png metadata when file is created from storage
* Added lock to checking duplicity process state to avoid a racing condition

### Fixes
* `Exiftool` metadata parse fix

## [1.6.1](https://github.com/anzusystems/core-dam-bundle/compare/1.6.0...1.6.1) (2024-01-16)
### Changes
* created unittests for AssetFile sys API

### Fixes
* Animated URL disabled for non-animated image

## [1.6.0](https://github.com/anzusystems/core-dam-bundle/compare/1.5.1...1.6.0) (2024-01-16)
### Feature
* Image public routes without slug. Removed deprecations.
* Asset sys API update. Now supports custom data and could generate pub…
* Renamed php version in github workflows.

## [1.5.0](https://github.com/anzusystems/core-dam-bundle/compare/1.5.0...1.5.1) (2024-01-10)
### Feature
* Added `number` case to `CustomFormElementType`

## [1.5.0](https://github.com/anzusystems/core-dam-bundle/compare/1.4.1...1.5.0) (2024-01-09)
### Feature
* php `8.3`
* `AssetFileRoute` allows to generate public routes to assets
* image response streaming
* animated gif response

## [1.4.1](https://github.com/anzusystems/core-dam-bundle/compare/1.4.0...1.4.1) (2023-12-19)
### Feature
* Serialize licenceId in AssetAdmListDto

## [1.4.0](https://github.com/anzusystems/core-dam-bundle/compare/1.3.3...1.4.0) (2023-12-18)
### Feature
* Added sys apis for create and get asset file from storage

## [1.3.3](https://github.com/anzusystems/core-dam-bundle/compare/1.3.2...1.3.3) (2023-11-28)
### Feature
* added API get asset by asset file

## [1.3.2](https://github.com/anzusystems/core-dam-bundle/compare/1.3.1...1.3.2) (2023-11-24)
### Fixes
* fixed tracking modification for async image processing

## [1.3.1](https://github.com/anzusystems/core-dam-bundle/compare/1.3.0...1.3.1) (2023-11-24)
### Fixes
* fixed allow list config - domain override

## [1.3.0](https://github.com/anzusystems/core-dam-bundle/compare/1.2.0...1.3.0) (2023-11-24)
### Features
* method createFromStorage now converts file if convert required
* public/private image feature
* removed deprecated column key from entity CustomFormElement
* custom BigIntType for doctrine (use int instead of string)
* allow extId in AssetLicence to be nullable
* PositionTrait position attribute changed from smallint to integer
* update common bundle -> added prefix
* package updates (psalm/ecs fixes)

## [1.2.0](https://github.com/anzusystems/core-dam-bundle/compare/1.1.1...1.2.0) (2023-10-26)
### Features
* cusomMetadata validation rework
* duplicate image preview rework
* youtube default language

### Bug fixes
* long displayTitle parse fix
* elastcseach keyword support
* youtube redistribute change language fix


## [1.1.1](https://github.com/anzusystems/core-dam-bundle/compare/1.1.0...1.1.1) (2023-07-21)
### Bug fixes
- Set YT thumbnail fix

## [1.1.0](https://github.com/anzusystems/core-dam-bundle/compare/1.0.0...1.1.0) (2023-06-22)

### Features
* asset create strategy from filesystem

### Bug Fixes
* get admin domain was previously hardcoded, apply ext system configuration

## [1.0.0](https://github.com/anzusystems/core-dam-bundle/releases/tag/1.0.0) (2023-06-20)

* main stable release
