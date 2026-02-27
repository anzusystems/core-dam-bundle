<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
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
}
