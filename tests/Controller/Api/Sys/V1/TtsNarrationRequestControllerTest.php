<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures as TestAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\TtsNarrationRequestSysUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/** SYS dispatch: validation, sync synthesis (full pipeline), and content-addressed deduplication. */
final class TtsNarrationRequestControllerTest extends AbstractApiController
{
    private const int LICENCE_ID = AssetLicenceFixtures::DEFAULT_LICENCE_ID;

    public function testDispatchValidationFailsOnEmptyText(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);

        $response = $client->post(TtsNarrationRequestSysUrl::dispatch(), [
            'text' => '',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'assetLicence' => self::LICENCE_ID,
        ]);

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'text' => [],
        ]);
    }

    public function testDispatchInitialCreatesActiveAsset(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);

        $response = $client->post(TtsNarrationRequestSysUrl::dispatch(), [
            'text' => 'This is a valid narration text for synthesis.',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'assetLicence' => self::LICENCE_ID,
            'title' => 'Test narration',
        ]);

        self::assertStatusCode($response, Response::HTTP_ACCEPTED);
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('pending', $data['status']);
        self::assertNotEmpty($data['assetId']);

        /** @var TtsAssetRepository $ttsAssetRepo */
        $ttsAssetRepo = $this->getService(TtsAssetRepository::class);
        $ttsAsset = $ttsAssetRepo->findByAssetIdJoined($data['assetId']);

        self::assertNotNull($ttsAsset, 'Synchronous handler should have produced the TTS asset.');
        self::assertTrue($ttsAsset->getStatus()->is(TtsAudioStatus::Active));
        self::assertNotNull($ttsAsset->getAsset()->getMainFile(), 'Master audio should be attached.');
    }

    public function testDispatchDuplicateReturnsExistingAsset(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        $payload = [
            'text' => 'Duplicate narration text reused across two dispatches.',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'assetLicence' => self::LICENCE_ID,
        ];

        $first = $client->post(TtsNarrationRequestSysUrl::dispatch(), $payload);
        self::assertStatusCode($first, Response::HTTP_ACCEPTED);
        $firstAssetId = json_decode((string) $first->getContent(), true)['assetId'];

        $second = $client->post(TtsNarrationRequestSysUrl::dispatch(), $payload);

        self::assertStatusCode($second, Response::HTTP_OK);
        $secondData = json_decode((string) $second->getContent(), true);
        self::assertSame('duplicate', $secondData['status']);
        self::assertSame($firstAssetId, $secondData['existingAssetId']);
    }

    #[DataProvider('dispatchAclDataProvider')]
    public function testDispatchAcl(int $licenceId, int $expectedStatus): void
    {
        $this->enableAclCheck();
        $this->grantPermission(User::ID_CMS_USER, DamPermissions::DAM_ASSET_CREATE);

        $response = $this->getApiClient(User::ID_CMS_USER)->post(TtsNarrationRequestSysUrl::dispatch(), [
            'text' => 'Narration text dispatched under the ACL check.',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'assetLicence' => $licenceId,
        ]);

        self::assertStatusCode($response, $expectedStatus);
    }

    public static function dispatchAclDataProvider(): array
    {
        return [
            'licence within user scope' => [
                'licenceId' => self::LICENCE_ID,
                'expectedStatus' => Response::HTTP_ACCEPTED,
            ],
            'licence outside user scope' => [
                'licenceId' => TestAssetLicenceFixtures::LICENCE_ID,
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
        ];
    }
}
