<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
use AnzuSystems\CoreDamBundle\Entity\JobSynchronizeImageChanged;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class JobControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testCreateJobSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/asset-file-reprocess-internal-flag', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
        ]);
        $this->assertStatusCode($response, Response::HTTP_CREATED);

        $job = $this->serializer->deserialize($response->getContent(), JobAssetFileReprocessInternalFlag::class);
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);
        $this->assertSame(AssetLicenceFixtures::LICENCE_ID, $job->getTargetLicenceId());
        $this->assertNull($job->getProcessFrom());
        $this->assertSame(500, $job->getBulkSize());
    }

    /**
     * @throws SerializerException
     */
    public function testCreateJobWithOptionsSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/asset-file-reprocess-internal-flag', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'processFrom' => '2025-01-15T10:00:00+00:00',
            'bulkSize' => 100,
        ]);
        $this->assertStatusCode($response, Response::HTTP_CREATED);

        $job = $this->serializer->deserialize($response->getContent(), JobAssetFileReprocessInternalFlag::class);
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);
        $this->assertSame(AssetLicenceFixtures::LICENCE_ID, $job->getTargetLicenceId());
        $this->assertNotNull($job->getProcessFrom());
        $this->assertSame('2025-01-15', $job->getProcessFrom()->format('Y-m-d'));
        $this->assertSame(100, $job->getBulkSize());
    }

    public function testCreateJobValidationFailure(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/asset-file-reprocess-internal-flag', [
            'targetLicenceId' => 0,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('targetLicenceId', $content['fields']);
    }

    public function testCreateJobBulkSizeValidation(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        // bulkSize too small
        $response = $client->post('/api/adm/v1/job/asset-file-reprocess-internal-flag', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'bulkSize' => 5,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('bulkSize', $content['fields']);

        // bulkSize too large
        $response = $client->post('/api/adm/v1/job/asset-file-reprocess-internal-flag', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'bulkSize' => 2000,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('bulkSize', $content['fields']);
    }

    /**
     * @throws SerializerException
     */
    public function testCreateSynchronizeImageChangedJobSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/synchronize-image-changed', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
        ]);
        $this->assertStatusCode($response, Response::HTTP_CREATED);

        $job = $this->serializer->deserialize($response->getContent(), JobSynchronizeImageChanged::class);
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $this->assertSame(AssetLicenceFixtures::LICENCE_ID, $job->getTargetLicenceId());
        $this->assertNull($job->getProcessFrom());
        $this->assertSame(500, $job->getBulkSize());
    }

    /**
     * @throws SerializerException
     */
    public function testCreateSynchronizeImageChangedJobWithOptionsSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/synchronize-image-changed', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'processFrom' => '2025-01-15T10:00:00+00:00',
            'bulkSize' => 100,
        ]);
        $this->assertStatusCode($response, Response::HTTP_CREATED);

        $job = $this->serializer->deserialize($response->getContent(), JobSynchronizeImageChanged::class);
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $this->assertSame(AssetLicenceFixtures::LICENCE_ID, $job->getTargetLicenceId());
        $this->assertNotNull($job->getProcessFrom());
        $this->assertSame('2025-01-15', $job->getProcessFrom()->format('Y-m-d'));
        $this->assertSame(100, $job->getBulkSize());
    }

    public function testCreateSynchronizeImageChangedJobValidationFailure(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post('/api/adm/v1/job/synchronize-image-changed', [
            'targetLicenceId' => 0,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('targetLicenceId', $content['fields']);
    }

    public function testCreateSynchronizeImageChangedJobBulkSizeValidation(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        // bulkSize too small
        $response = $client->post('/api/adm/v1/job/synchronize-image-changed', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'bulkSize' => 5,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('bulkSize', $content['fields']);

        // bulkSize too large
        $response = $client->post('/api/adm/v1/job/synchronize-image-changed', [
            'targetLicenceId' => AssetLicenceFixtures::LICENCE_ID,
            'bulkSize' => 2000,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('fields', $content);
        $this->assertArrayHasKey('bulkSize', $content['fields']);
    }
}
