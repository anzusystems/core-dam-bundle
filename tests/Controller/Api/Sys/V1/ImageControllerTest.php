<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures as BlogImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ImageSysUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ImageControllerTest extends AbstractApiController
{
    /**
     * @param list<string> $imageFileIds
     */
    #[DataProvider('useAccessDataProvider')]
    public function testUseAccess(
        ?int $grantUserId,
        int $requestUserId,
        array $imageFileIds,
        int $expectedStatus,
        ?int $targetAssetLicence = null,
        bool $grantCreate = false,
    ): void {
        $this->enableAclCheck();
        if (null !== $grantUserId) {
            $this->grantPermission(
                $grantUserId,
                ...($grantCreate ? [DamPermissions::DAM_ASSET_READ, DamPermissions::DAM_ASSET_CREATE] : [DamPermissions::DAM_ASSET_READ]),
            );
        }

        $response = $this->getApiClient($requestUserId)->post(ImageSysUrl::use(), [
            'items' => array_map(static fn (string $imageFileId): array => [
                'imageFileId' => $imageFileId,
                'targetAssetLicence' => $targetAssetLicence,
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
            'target licence without the create permission' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedStatus' => Response::HTTP_FORBIDDEN,
                'targetAssetLicence' => AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
            ],
            'target licence with the create permission' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'imageFileIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedStatus' => Response::HTTP_OK,
                'targetAssetLicence' => AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
                'grantCreate' => true,
            ],
        ];
    }

    public function testUseReturnsTheRequestedFile(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), [
            'items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1]],
        ]);

        self::assertStatusCode($response, Response::HTTP_OK);
        $result = json_decode((string) $response->getContent(), true)['results'][App::ZERO];
        self::assertSame(ImageFixtures::IMAGE_ID_1_1, $result['imageFileId']);
        self::assertNull($result['takenOverFromId']);
    }

    public function testUseOfAPhotoWhoseLicenceForbidsDirectUseWithoutATargetIsRefused(): void
    {
        $this->findFixturePhoto()->getLicence()->getFlags()->setDirectUseAllowed(false);
        $this->entityManager->flush();

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), [
            'items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1]],
        ]);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertForbiddenOperationError((string) $response->getContent(), ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
    }

    #[DataProvider('useValidationFailureDataProvider')]
    public function testUseValidationFailure(array $requestJson, array $validationErrors): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::use(), $requestJson);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), $validationErrors);
    }

    public static function useValidationFailureDataProvider(): array
    {
        return [
            'no items' => [
                'requestJson' => ['items' => []],
                'validationErrors' => ['items' => [ValidationException::ERROR_FIELD_LENGTH_MIN]],
            ],
            'more items than a batch may carry' => [
                'requestJson' => ['items' => array_fill(
                    App::ZERO,
                    ImageUseRequestDto::MAX_ITEMS + 1,
                    ['imageFileId' => ImageFixtures::IMAGE_ID_1_1],
                )],
                'validationErrors' => ['items' => [ValidationException::ERROR_FIELD_LENGTH_MAX]],
            ],
            'photo unknown or deleted in DAM' => [
                'requestJson' => ['items' => [['imageFileId' => Uuid::v4()->toRfc4122()]]],
                'validationErrors' => ['items[0].imageFile' => [ValidationException::ERROR_FIELD_EMPTY]],
            ],
            'target licence of another ext system than the photo' => [
                'requestJson' => ['items' => [[
                    'imageFileId' => ImageFixtures::IMAGE_ID_1_1,
                    'targetAssetLicence' => AssetLicenceFixtures::LICENCE_ID,
                ]]],
                'validationErrors' => ['items[0].targetAssetLicence' => [ValidationException::ERROR_INVALID_LICENCE]],
            ],
            'force take over without a target licence' => [
                'requestJson' => ['items' => [['imageFileId' => ImageFixtures::IMAGE_ID_1_1, 'forceTakeOver' => true]]],
                'validationErrors' => ['items[0].targetAssetLicence' => [ValidationException::ERROR_FIELD_EMPTY]],
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

    /**
     * @param list<string> $permissions
     */
    #[DataProvider('copyJobAclDataProvider')]
    public function testCopyJobAcl(array $permissions, int $targetLicenceId, string $imageFileId, int $expectedStatus): void
    {
        $this->enableAclCheck();
        $this->grantPermission(User::ID_CMS_USER, ...$permissions);

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::copyJob(), [
            'targetAssetLicence' => $targetLicenceId,
            'items' => [['imageFile' => $imageFileId]],
        ]);

        self::assertStatusCode($response, $expectedStatus);
    }

    public static function copyJobAclDataProvider(): array
    {
        return [
            'without permission' => [
                'permissions' => [],
                'targetLicenceId' => BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID,
                'imageFileId' => ImageFixtures::IMAGE_ID_1_1,
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
            'licences within user scope' => [
                'permissions' => [DamPermissions::DAM_ASSET_CREATE, DamPermissions::DAM_ASSET_READ],
                'targetLicenceId' => BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID,
                'imageFileId' => ImageFixtures::IMAGE_ID_1_1,
                'expectedStatus' => Response::HTTP_OK,
            ],
            'licences outside user scope' => [
                'permissions' => [DamPermissions::DAM_ASSET_CREATE, DamPermissions::DAM_ASSET_READ],
                'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
                'imageFileId' => BlogImageFixtures::IMAGE_ID_1,
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
        ];
    }

    private function findFixturePhoto(): ImageFile
    {
        $this->entityManager->clear();
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image;
    }

}
