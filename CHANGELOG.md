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
