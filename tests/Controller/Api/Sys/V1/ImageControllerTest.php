<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\Contracts\Security\Grant;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageReleaseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures as BlogImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ImageSysUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ImageControllerTest extends AbstractApiController
{
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '01994000-0000-7000-8000-000000000001';
    private const string RECEIVER_NAME = 'gallery';
    private const string RECEIVER_ID = '42';

    private AssetFileManager $assetFileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetFileManager = $this->getService(AssetFileManager::class);
    }

    /**
     * @param list<string> $imageFileIds
     */
    #[DataProvider('useAccessDataProvider')]
    public function testUseAccess(?int $grantUserId, int $requestUserId, array $imageFileIds, int $expectedStatus): void
    {
        $this->enableAclCheck();
        if (null !== $grantUserId) {
            $this->grantPermission($grantUserId, DamPermissions::DAM_ASSET_READ);
        }

        $response = $this->getApiClient($requestUserId)->post(ImageSysUrl::use(), [
            'holder' => self::holder(),
            'items' => array_map(static fn (string $imageFileId): array => [
                'imageFileId' => $imageFileId,
            ], $imageFileIds),
        ]);

        self::assertStatusCode($response, $expectedStatus);
    }

    public static function useAccessDataProvider(): array
    {
        return [
            'user without the read permission' => [
                'grantUserId' => null,
                'requestUserId' => User::ID_CONSOLE,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
            'user without the licence of the photo' => [
                'grantUserId' => User::ID_CONSOLE,
                'requestUserId' => User::ID_CONSOLE,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
            'batch with a photo of a licence the user does not have' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1, BlogImageFixtures::IMAGE_ID_1],
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
            'user with the read permission and the licence of the photo' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedStatus' => Response::HTTP_OK,
            ],
        ];
    }

    public function testUseClaimsTheSingleUsePhotoForTheHolder(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), [
            'holder' => self::holder(),
            'items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1]],
        ]);

        self::assertStatusCode($response, Response::HTTP_OK);
        $result = json_decode((string) $response->getContent(), true)['results'][App::ZERO];
        self::assertSame(ImageFixtures::IMAGE_ID_1_1, $result['imageFileId']);
        self::assertTrue($result['singleUse']);
        self::assertNull($result['takenOverFromId']);
        $this->assertHolder(self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testUseOfAPhotoWhoseLicenceForbidsDirectUseWithoutATargetIsRefused(): void
    {
        $this->forbidDirectUseOfTheFixtureLicence();

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), [
            'holder' => self::holder(),
            'items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1]],
        ]);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertForbiddenOperationError((string) $response->getContent(), ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        $this->assertHolder(App::EMPTY_STRING, App::EMPTY_STRING);
    }

    #[DataProvider('useValidationFailureDataProvider')]
    public function testUseValidationFailure(array $requestJson, array $validationErrors): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), $requestJson);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), $validationErrors);
        $this->assertHolder(App::EMPTY_STRING, App::EMPTY_STRING);
    }

    public static function useValidationFailureDataProvider(): array
    {
        $validRequest = [
            'holder' => self::holder(),
            'items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1]],
        ];

        return [
            'no items' => [
                'requestJson' => ['items' => []] + $validRequest,
                'validationErrors' => ['items' => [ValidationException::ERROR_FIELD_LENGTH_MIN]],
            ],
            'holder without an id' => [
                'requestJson' => ['holder' => ['name' => self::HOLDER_NAME, 'id' => App::EMPTY_STRING]] + $validRequest,
                'validationErrors' => ['holder.id' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'more items than a batch may carry' => [
                'requestJson' => ['items' => array_fill(
                    App::ZERO,
                    ImageUseRequestDto::MAX_ITEMS + 1,
                    ['imageFileId' => ImageFixtures::IMAGE_ID_1_1],
                )] + $validRequest,
                'validationErrors' => ['items' => [ValidationException::ERROR_FIELD_LENGTH_MAX]],
            ],
            'photo unknown or deleted in DAM' => [
                'requestJson' => ['items' => [['imageFileId' => Uuid::v4()->toRfc4122()]]] + $validRequest,
                'validationErrors' => ['items[0].imageFile' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'target licence of another ext system than the photo' => [
                'requestJson' => ['items' => [[
                    'imageFileId' => ImageFixtures::IMAGE_ID_1_1,
                    'targetAssetLicence' => AssetLicenceFixtures::LICENCE_ID,
                ]]] + $validRequest,
                'validationErrors' => ['items[0].targetAssetLicence' => [ValidationException::ERROR_INVALID_LICENCE]],
            ],
        ];
    }

    #[DataProvider('copyJobValidationFailureDataProvider')]
    public function testCopyJobValidationFailure(array $requestJson, array $validationErrors): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::copyJob(), $requestJson);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), $validationErrors);
    }

    public static function copyJobValidationFailureDataProvider(): array
    {
        return [
            'photo unknown or deleted in DAM' => [
                'requestJson' => [
                    'targetAssetLicence' => BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID,
                    'items' => [['imageFile' => Uuid::v4()->toRfc4122()]],
                ],
                'validationErrors' => ['items[0].imageFile' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'photo of another ext system than the target licence' => [
                'requestJson' => [
                    'targetAssetLicence' => AssetLicenceFixtures::LICENCE_ID,
                    'items' => [['imageFile' => ImageFixtures::IMAGE_ID_1_1]],
                ],
                'validationErrors' => ['targetAssetLicence' => [ValidationException::ERROR_INVALID_LICENCE]],
            ],
        ];
    }

    #[DataProvider('releaseValidationFailureDataProvider')]
    public function testReleaseValidationFailure(array $requestJson, array $validationErrors): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::release(), $requestJson);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), $validationErrors);
    }

    public static function releaseValidationFailureDataProvider(): array
    {
        $validRequest = [
            'holder' => self::holder(),
            'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
        ];

        return [
            'holder without an id' => [
                'requestJson' => ['holder' => ['name' => self::HOLDER_NAME, 'id' => App::EMPTY_STRING]] + $validRequest,
                'validationErrors' => ['holder.id' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'more photos than a batch may carry' => [
                'requestJson' => ['imageFileIds' => array_fill(
                    App::ZERO,
                    ImageReleaseRequestDto::MAX_ITEMS + 1,
                    ImageFixtures::IMAGE_ID_1_1,
                )] + $validRequest,
                'validationErrors' => ['imageFileIds' => [ValidationException::ERROR_FIELD_LENGTH_MAX]],
            ],
        ];
    }

    #[DataProvider('transferValidationFailureDataProvider')]
    public function testTransferValidationFailure(array $requestJson, array $validationErrors): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::transfer(), $requestJson);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), $validationErrors);
    }

    public static function transferValidationFailureDataProvider(): array
    {
        $validRequest = [
            'from' => self::holder(),
            'to' => ['name' => self::RECEIVER_NAME, 'id' => self::RECEIVER_ID],
            'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
        ];

        return [
            'receiver without an id' => [
                'requestJson' => ['to' => ['name' => self::RECEIVER_NAME, 'id' => App::EMPTY_STRING]] + $validRequest,
                'validationErrors' => ['to.id' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'more photos than a batch may carry' => [
                'requestJson' => ['imageFileIds' => array_fill(
                    App::ZERO,
                    ImageTransferRequestDto::MAX_ITEMS + 1,
                    ImageFixtures::IMAGE_ID_1_1,
                )] + $validRequest,
                'validationErrors' => ['imageFileIds' => [ValidationException::ERROR_FIELD_LENGTH_MAX]],
            ],
        ];
    }

    /**
     * @param list<string> $imageFileIds
     */
    #[DataProvider('releaseDataProvider')]
    public function testRelease(array $imageFileIds, string $expectedHolderName, string $expectedHolderId): void
    {
        $this->claimTheFixturePhoto();

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::release(), [
            'holder' => self::holder(),
            'imageFileIds' => $imageFileIds,
        ]);

        self::assertStatusCode($response, Response::HTTP_NO_CONTENT);
        $this->assertHolder($expectedHolderName, $expectedHolderId);
    }

    public static function releaseDataProvider(): array
    {
        return [
            'photo held by the holder is freed' => [
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedHolderName' => App::EMPTY_STRING,
                'expectedHolderId' => App::EMPTY_STRING,
            ],
            'photo deleted in DAM is accepted and nothing is freed' => [
                'imageFileIds' => [Uuid::v4()->toRfc4122()],
                'expectedHolderName' => self::HOLDER_NAME,
                'expectedHolderId' => self::HOLDER_ID,
            ],
        ];
    }

    public function testTransferHandsThePhotoToTheReceiver(): void
    {
        $this->claimTheFixturePhoto();

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::transfer(), [
            'from' => self::holder(),
            'to' => ['name' => self::RECEIVER_NAME, 'id' => self::RECEIVER_ID],
            'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
        ]);

        self::assertStatusCode($response, Response::HTTP_OK);
        $content = json_decode((string) $response->getContent(), true);
        self::assertArrayNotHasKey('transferred', $content);
        self::assertSame([], $content['notHeldByFrom']);
        $this->assertHolder(self::RECEIVER_NAME, self::RECEIVER_ID);
    }

    /**
     * @return array{name: string, id: string}
     */
    private static function holder(): array
    {
        return ['name' => self::HOLDER_NAME, 'id' => self::HOLDER_ID];
    }

    private function assertHolder(string $holderName, string $holderId): void
    {
        $this->entityManager->clear();
        $attributes = $this->findFixturePhoto()->getAssetAttributes();

        self::assertSame($holderName, $attributes->getUsedByHolderName());
        self::assertSame($holderId, $attributes->getUsedByHolderId());
    }

    private function claimTheFixturePhoto(): void
    {
        $this->assetFileManager->updateUsage(
            $this->findFixturePhoto(),
            UsageClaim::fromHolder(new ImageHolderDto()->setName(self::HOLDER_NAME)->setId(self::HOLDER_ID)),
            usedByCheckAfter: AssetFileManager::nextUsageCheck(),
        );
    }

    private function forbidDirectUseOfTheFixtureLicence(): void
    {
        $this->findFixturePhoto()->getLicence()->getFlags()->setDirectUseAllowed(false);
        $this->entityManager->flush();
    }

    private function findFixturePhoto(): ImageFile
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image;
    }

    private function enableAclCheck(): void
    {
        /** @var ConfigurationProvider $configurationProvider */
        $configurationProvider = static::getContainer()->get(ConfigurationProvider::class);
        $settings = $configurationProvider->getSettings();

        $aclEnabledSettings = new SettingsConfiguration(
            elasticIndexPrefix: $settings->getElasticIndexPrefix(),
            notificationsConfig: $settings->getNotificationsConfig(),
            elasticLanguageDictionaries: $settings->getElasticLanguageDictionaries(),
            apiDomainKey: $settings->getApiDomainKey(),
            redirectDomain: $settings->getRedirectDomain(),
            youtubeApiKey: $settings->getYoutubeApiKey(),
            defaultExtSystemId: $settings->getDefaultExtSystemId(),
            defaultAssetLicenceId: $settings->getDefaultAssetLicenceId(),
            allowSelectExtSystem: $settings->isAllowSelectExtSystem(),
            allowSelectLicenceId: $settings->isAllowSelectLicenceId(),
            maxBulkItemCount: $settings->getMaxBulkItemCount(),
            imageChunkConfig: $settings->getImageChunkConfig(),
            aclCheckEnabled: true,
            userAuthType: $settings->getUserAuthType(),
            adminAllowListName: $settings->getAdminAllowListName(),
            distributionAuthRedirectUrl: $settings->getDistributionAuthRedirectUrl(),
            limitedAssetLicenceFilesCount: $settings->getLimitedAssetLicenceFilesCount(),
            notFoundImageId: $settings->getNotFoundImageId(),
        );

        new ReflectionProperty(ConfigurationProvider::class, 'settingsConfiguration')
            ->setValue($configurationProvider, $aclEnabledSettings);
    }

    private function grantPermission(int $userId, string $permission): void
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $user);

        $user->setPermissions([$permission => Grant::ALLOW]);
        $this->entityManager->flush();
    }
}
