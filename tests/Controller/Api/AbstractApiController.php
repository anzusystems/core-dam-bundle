<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api;

use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Controller\AbstractController;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

abstract class AbstractApiController extends AbstractController
{
    protected Serializer $serializer;
    protected static KernelBrowser $client;

    /** @psalm-var array<string, ApiClient> */
    private array $clients = [];

    public function getApiClient(?int $userId = null): ApiClient
    {
        $key = $userId ?? 'anonymous';
        if (false === isset($this->clients[$key])) {
            $this->clients[$key] = new ApiClient(
                static::$client,
                $this->serializer,
                $userId
            );
        }
        return $this->clients[$key];
    }

    protected function setUp(): void
    {
        parent::setUp();

        static::$client = static::getContainer()->get('test.client');
        static::$client->disableReboot();

        /** @var Serializer $serializer */
        $serializer = static::getContainer()->get(Serializer::class);
        $this->serializer = $serializer;
    }

    protected function assertValidationErrors(array $responseContent, array $expectedValidationErrors): void
    {
        $this->assertArrayHasKey('error', $responseContent);
        $this->assertArrayHasKey('fields', $responseContent);
        $this->assertSame('validation_failed', $responseContent['error']);
        $this->assertSameSize($expectedValidationErrors, $responseContent['fields']);
        foreach ($expectedValidationErrors as $fieldName => $errors) {
            foreach ($errors as $error) {
                $this->assertContains($error, $responseContent['fields'][$fieldName]);
            }
        }
    }

    protected function assertForbiddenOperationError(string $responseContent, string $error): void
    {
        $data = json_decode($responseContent, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('detail', $data);
        $this->assertSame($error, $data['detail']);
    }
}
