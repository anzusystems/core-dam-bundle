<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\Embeds\JwTexts;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures as TestImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\VideoSysUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class VideoControllerTest extends AbstractApiController
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';
    private const string YOUTUBE_DISTRIBUTION_SERVICE = 'youtube_cms_main';
    private const string DOWNLOAD_URL = 'http://core-dam.sme.localhost/download-file?file=video_example.mp4';

    private VideoFileRepository $videoFileRepository;
    private DistributionRepository $distributionRepository;
    private AssetFileRouteRepository $assetFileRouteRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->videoFileRepository = $this->getService(VideoFileRepository::class);
        $this->distributionRepository = $this->getService(DistributionRepository::class);
        $this->assetFileRouteRepository = $this->getService(AssetFileRouteRepository::class);
    }

    public function testCreateFromUrl(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('create'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE],
                'imagePreviewAssetFileId' => ImageFixtures::IMAGE_ID_1_1,
            ])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame(AssetType::Video->value, $data['assetType']);
        self::assertSame(AssetFileProcessStatus::Processed->value, $data['assetFileStatus']);

        $this->entityManager->clear();
        $videoFile = $this->videoFileRepository->find($data['id']);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        self::assertTrue($videoFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
        self::assertSame(ImageFixtures::IMAGE_ID_1_1, $videoFile->getImagePreview()?->getImageFile()->getId());

        $distribution = $this->distributionRepository->findByAssetFileAndDistributionService(
            $data['id'],
            self::DISTRIBUTION_SERVICE
        );
        self::assertInstanceOf(JwDistribution::class, $distribution);
        self::assertSame(User::ID_CMS_USER, $distribution->getCreatedBy()->getId());
        self::assertNull($this->assetFileRouteRepository->findMainByAssetFile($data['id']));
    }

    public function testCreateFromUrlDedup(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        $url = self::uniqueUrl('dedup');

        $firstResponse = $client->post(VideoSysUrl::createFromUrl(), $this->payload($url));
        self::assertStatusCode($firstResponse, Response::HTTP_OK);
        $firstData = json_decode((string) $firstResponse->getContent(), true);
        $this->entityManager->clear();
        self::assertCount(0, $this->distributionRepository->findByAssetFile($firstData['id']));

        $payload = $this->payload($url, ['distributionServices' => [self::DISTRIBUTION_SERVICE]]);
        $secondResponse = $client->post(VideoSysUrl::createFromUrl(), $payload);
        self::assertStatusCode($secondResponse, Response::HTTP_OK);
        $secondData = json_decode((string) $secondResponse->getContent(), true);

        self::assertSame($firstData['assetId'], $secondData['assetId']);
        $this->entityManager->clear();
        self::assertCount(1, $this->videoFileRepository->findBy(['assetAttributes.originUrl' => $url]));
        self::assertCount(1, $this->distributionRepository->findByAssetFile($firstData['id']));

        $thirdResponse = $client->post(VideoSysUrl::createFromUrl(), $payload);
        self::assertStatusCode($thirdResponse, Response::HTTP_OK);
        $this->entityManager->clear();
        self::assertCount(1, $this->distributionRepository->findByAssetFile($firstData['id']));
    }

    public function testCreateFromUrlDedupWhileUploadedDoesNothing(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        $url = self::uniqueUrl('dedup-uploaded');
        $firstData = $this->createVideoWithStatus($client, $url, AssetFileProcessStatus::Uploaded);

        $secondResponse = $client->post(
            VideoSysUrl::createFromUrl(),
            $this->payload($url, ['distributionServices' => [self::DISTRIBUTION_SERVICE]])
        );
        self::assertStatusCode($secondResponse, Response::HTTP_OK);
        $secondData = json_decode((string) $secondResponse->getContent(), true);

        self::assertSame($firstData['id'], $secondData['id']);
        self::assertSame($firstData['assetId'], $secondData['assetId']);
        $this->entityManager->clear();
        self::assertCount(1, $this->videoFileRepository->findBy(['assetAttributes.originUrl' => $url]));
        $videoFile = $this->videoFileRepository->find($firstData['id']);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        self::assertTrue($videoFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Uploaded));
        self::assertCount(0, $this->distributionRepository->findByAssetFile($firstData['id']));
    }

    public function testCreateFromUrlDedupWhileStoredDoesNothing(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        $url = self::uniqueUrl('dedup-stored');
        $firstData = $this->createVideoWithStatus($client, $url, AssetFileProcessStatus::Stored);

        $secondResponse = $client->post(
            VideoSysUrl::createFromUrl(),
            $this->payload($url, ['distributionServices' => [self::DISTRIBUTION_SERVICE]])
        );
        self::assertStatusCode($secondResponse, Response::HTTP_OK);
        $secondData = json_decode((string) $secondResponse->getContent(), true);

        self::assertSame($firstData['id'], $secondData['id']);
        self::assertSame($firstData['assetId'], $secondData['assetId']);
        $this->entityManager->clear();
        self::assertCount(1, $this->videoFileRepository->findBy(['assetAttributes.originUrl' => $url]));
        $videoFile = $this->videoFileRepository->find($firstData['id']);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        self::assertTrue($videoFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Stored));
        self::assertCount(0, $this->distributionRepository->findByAssetFile($firstData['id']));
    }

    public function testCreateFromUrlDuplicateDistributionServices(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('duplicate-services'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE, self::DISTRIBUTION_SERVICE],
            ])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'distributionServices' => [ValidationException::ERROR_FIELD_UNIQUE],
        ]);
    }

    public function testCreateFromUrlCutsJwTexts(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('cut-texts'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE],
                'customData' => [
                    'title' => str_repeat('t', 150),
                    'description' => str_repeat('d', 6_000),
                ],
            ])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame(JwTexts::TITLE_MAX_LENGTH, mb_strlen($data['customData']['title']));
        self::assertSame(JwTexts::DESCRIPTION_MAX_LENGTH, mb_strlen($data['customData']['description']));

        $this->entityManager->clear();
        $videoFile = $this->videoFileRepository->find($data['id']);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $customData = $videoFile->getAsset()->getMetadata()->getCustomData();
        self::assertSame(JwTexts::TITLE_MAX_LENGTH, mb_strlen($customData['title']));
        self::assertSame(JwTexts::DESCRIPTION_MAX_LENGTH, mb_strlen($customData['description']));

        $distribution = $this->distributionRepository->findByAssetFileAndDistributionService(
            $data['id'],
            self::DISTRIBUTION_SERVICE
        );
        self::assertInstanceOf(JwDistribution::class, $distribution);
        self::assertSame(JwTexts::TITLE_MAX_LENGTH, mb_strlen($distribution->getTexts()->getTitle()));
        self::assertSame(JwTexts::DESCRIPTION_MAX_LENGTH, mb_strlen($distribution->getTexts()->getDescription()));
    }

    public function testCreateFromUrlKeepsJwTextsWithoutDistribution(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('keep-texts'), [
                'customData' => ['title' => str_repeat('t', 150)],
            ])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame(150, mb_strlen($data['customData']['title']));
    }

    public function testCreateFromUrlInvalidDistributionService(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('invalid-service'), ['distributionServices' => [self::YOUTUBE_DISTRIBUTION_SERVICE]])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'distributionServices' => [ValidationException::ERROR_FIELD_INVALID],
        ]);
    }

    public function testCreateFromUrlInvalidCustomDataType(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);

        $response = $client->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('invalid-title'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE],
                'customData' => ['title' => ['array'], 'description' => 'Description'],
            ])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'customData' => [ValidationException::ERROR_FIELD_INVALID],
        ]);

        $response = $client->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('blank-author'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE],
                'authors' => ['  '],
            ])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'authors' => [ValidationException::ERROR_FIELD_EMPTY],
        ]);
    }

    #[DataProvider('invalidJwTextsDataProvider')]
    public function testCreateFromUrlInvalidJwTexts(array $customData, string $error): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('invalid-texts'), [
                'distributionServices' => [self::DISTRIBUTION_SERVICE],
                'customData' => $customData,
            ])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'customData' => [$error],
        ]);
    }

    public static function invalidJwTextsDataProvider(): array
    {
        return [
            'missing description' => [
                'customData' => ['title' => 'Title'],
                'error' => ValidationException::ERROR_FIELD_INVALID,
            ],
            'empty title' => [
                'customData' => ['title' => '', 'description' => 'Description'],
                'error' => ValidationException::ERROR_FIELD_EMPTY,
            ],
            'blank description' => [
                'customData' => ['title' => 'Title', 'description' => '  '],
                'error' => ValidationException::ERROR_FIELD_EMPTY,
            ],
        ];
    }

    public function testCreateFromUrlPreviewFromOtherLicence(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('other-licence'), [
                'imagePreviewAssetFileId' => TestImageFixtures::IMAGE_ID_5,
            ])
        );

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode((string) $response->getContent(), true), [
            'imagePreviewAssetFileId' => [ValidationException::ERROR_FIELD_INVALID],
        ]);
    }

    public function testCreateFromUrlIgnoresGeneratePublicRoute(): void
    {
        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            VideoSysUrl::createFromUrl(),
            $this->payload(self::uniqueUrl('public-route'), ['generatePublicRoute' => true])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $data = json_decode((string) $response->getContent(), true);
        self::assertNull($this->assetFileRouteRepository->findMainByAssetFile($data['id']));
    }

    private function createVideoWithStatus(ApiClient $client, string $url, AssetFileProcessStatus $status): array
    {
        $response = $client->post(VideoSysUrl::createFromUrl(), $this->payload($url));
        self::assertStatusCode($response, Response::HTTP_OK);
        $data = json_decode((string) $response->getContent(), true);

        $videoFile = $this->videoFileRepository->find($data['id']);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $videoFile->getAssetAttributes()->setStatus($status);
        $this->entityManager->flush();

        return $data;
    }

    private static function uniqueUrl(string $key): string
    {
        return self::DOWNLOAD_URL . '&case=' . $key;
    }

    private function payload(string $url, array $override = []): array
    {
        return [
            'licence' => 100_000,
            'url' => $url,
            'customData' => [
                'title' => 'Title',
                'description' => 'Description',
            ],
            'authors' => ['BANG Showbiz'],
            ...$override,
        ];
    }
}
