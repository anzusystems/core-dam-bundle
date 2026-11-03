<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures as BlogImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ImageSysUrl;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ImageControllerTest extends AbstractApiController
{
    private const string REQUESTED_AT = '2026-03-01T10:15:00.000000Z';
    private const string REQUESTED_AT_STORED = '2026-03-01 10:15:00';
    private const string LATER_REQUESTED_AT = '2026-04-01T12:00:00.000000Z';
    private const string ALREADY_USED_AT = '2020-05-04 08:30:00';

    private AssetFileRepository $assetFileRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetFileRepository = $this->getService(AssetFileRepository::class);
    }

    public function testFirstUseHandlesMixedBatch(): void
    {
        $this->presetFirstUsedAt(ImageFixtures::IMAGE_ID_1_2);

        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::firstUse(), [
            'items' => [
                self::item(ImageFixtures::IMAGE_ID_1_1),
                self::item(ImageFixtures::IMAGE_ID_1_2),
                self::item(Uuid::v4()->toRfc4122()),
            ],
        ]);

        self::assertStatusCode($response, Response::HTTP_NO_CONTENT);
        self::assertSame(self::REQUESTED_AT_STORED, $this->reloadFirstUsedAt(ImageFixtures::IMAGE_ID_1_1));
        self::assertSame(self::ALREADY_USED_AT, $this->reloadFirstUsedAt(ImageFixtures::IMAGE_ID_1_2));
    }

    #[DataProvider('maxItemCountDataProvider')]
    public function testFirstUseEnforcesMaxItemCount(int $itemCount, int $expectedStatus, bool $expectValidationError): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(ImageSysUrl::firstUse(), [
            'items' => self::unknownItems($itemCount),
        ]);

        self::assertStatusCode($response, $expectedStatus);
        if ($expectValidationError) {
            $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
                'items' => [],
            ]);
        }
    }

    public static function maxItemCountDataProvider(): array
    {
        return [
            'within_limit' => [
                'itemCount' => ImageFirstUseRequestDto::MAX_ITEMS,
                'expectedStatus' => Response::HTTP_NO_CONTENT,
                'expectValidationError' => false,
            ],
            'above_limit' => [
                'itemCount' => ImageFirstUseRequestDto::MAX_ITEMS + 1,
                'expectedStatus' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectValidationError' => true,
            ],
        ];
    }

    public function testFirstUseIsWriteOnceOnRepeatedCall(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);

        $firstResponse = $client->post(ImageSysUrl::firstUse(), [
            'items' => [self::item(ImageFixtures::IMAGE_ID_3)],
        ]);

        self::assertStatusCode($firstResponse, Response::HTTP_NO_CONTENT);
        self::assertSame(self::REQUESTED_AT_STORED, $this->reloadFirstUsedAt(ImageFixtures::IMAGE_ID_3));

        $secondResponse = $client->post(ImageSysUrl::firstUse(), [
            'items' => [[
                'damId' => ImageFixtures::IMAGE_ID_3,
                'firstUsedAt' => self::LATER_REQUESTED_AT,
            ]],
        ]);

        self::assertStatusCode($secondResponse, Response::HTTP_NO_CONTENT);
        self::assertSame(self::REQUESTED_AT_STORED, $this->reloadFirstUsedAt(ImageFixtures::IMAGE_ID_3));
    }

    /**
     * @param list<string> $itemDamIds
     * @param list<string> $expectedWrittenDamIds
     */
    #[DataProvider('aclScopeDataProvider')]
    public function testFirstUseAclScoping(
        ?int $grantUserId,
        int $requestUserId,
        array $itemDamIds,
        array $expectedWrittenDamIds,
    ): void {
        $this->enableAclCheck();
        if (null !== $grantUserId) {
            $this->grantPermission($grantUserId, DamPermissions::DAM_IMAGE_UPDATE);
        }

        $response = $this->getApiClient($requestUserId)->post(ImageSysUrl::firstUse(), [
            'items' => array_map(static fn (string $damId): array => self::item($damId), $itemDamIds),
        ]);

        self::assertStatusCode($response, Response::HTTP_NO_CONTENT);
        foreach ($itemDamIds as $damId) {
            self::assertSame(
                in_array($damId, $expectedWrittenDamIds, true) ? self::REQUESTED_AT_STORED : null,
                $this->reloadFirstUsedAt($damId),
            );
        }
    }

    public static function aclScopeDataProvider(): array
    {
        return [
            'skipped_without_permission' => [
                'grantUserId' => null,
                'requestUserId' => User::ID_CONSOLE,
                'itemDamIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedWrittenDamIds' => [],
            ],
            'skipped_for_licence_outside_user_scope' => [
                'grantUserId' => User::ID_CONSOLE,
                'requestUserId' => User::ID_CONSOLE,
                'itemDamIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedWrittenDamIds' => [],
            ],
            'allowed_for_licence_within_user_scope' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'itemDamIds' => [ImageFixtures::IMAGE_ID_1_1],
                'expectedWrittenDamIds' => [ImageFixtures::IMAGE_ID_1_1],
            ],
            'mixed_licences_writes_only_authorized' => [
                'grantUserId' => User::ID_CMS_USER,
                'requestUserId' => User::ID_CMS_USER,
                'itemDamIds' => [ImageFixtures::IMAGE_ID_1_1, BlogImageFixtures::IMAGE_ID_1],
                'expectedWrittenDamIds' => [ImageFixtures::IMAGE_ID_1_1],
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

    /**
     * @return array<string, string>
     */
    private static function item(string $damId): array
    {
        return [
            'damId' => $damId,
            'firstUsedAt' => self::REQUESTED_AT,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function unknownItems(int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = self::item(Uuid::v4()->toRfc4122());
        }

        return $items;
    }

    private function presetFirstUsedAt(string $damId): void
    {
        $assetFile = $this->assetFileRepository->find($damId);
        self::assertNotNull($assetFile);

        $assetFile->setFirstUsedAt(new DateTimeImmutable(self::ALREADY_USED_AT));
        $this->entityManager->flush();
    }

    private function reloadFirstUsedAt(string $damId): ?string
    {
        $this->entityManager->clear();
        $assetFile = $this->assetFileRepository->find($damId);
        self::assertNotNull($assetFile);

        return $assetFile->getFirstUsedAt()?->format('Y-m-d H:i:s');
    }
}
