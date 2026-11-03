<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api;

use AnzuSystems\Contracts\Security\Grant;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Controller\AbstractController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use ReflectionProperty;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiController extends AbstractController
{
    protected Serializer $serializer;
    protected static KernelBrowser $client;

    /** @psalm-var array<string, ApiClient> */
    private array $clients = [];

    public function getApiClient(?int $newClient = null): ApiClient
    {
        $key = $newClient ?? 'anonymous';
        if (false === isset($this->clients[$key])) {
            $this->clients[$key] = new ApiClient(
                static::$client,
                $this->serializer,
                $newClient
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

    public static function assertStatusCode(Response $response, int $expectedStatus): void
    {
        self::assertSame($expectedStatus, $response->getStatusCode(), 'Response status code not same. Response message: ' . (string) $response->getContent());
    }

    protected function assertForbiddenOperationError(string $responseContent, string $error): void
    {
        $data = json_decode($responseContent, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('detail', $data);
        $this->assertSame($error, $data['detail']);
    }

    /**
     * ACL checks are disabled by default in the test environment (tests/config/packages/anzu_systems_core_dam.yaml).
     * Rebuild the real settings with acl_check_enabled flipped on and swap them into the shared
     * ConfigurationProvider singleton, so this test method exercises the real voter chain.
     */
    protected function enableAclCheck(): void
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

        $settingsConfigurationProperty = new ReflectionProperty(ConfigurationProvider::class, 'settingsConfiguration');
        $settingsConfigurationProperty->setValue($configurationProvider, $aclEnabledSettings);
    }

    protected function grantPermission(int $userId, string ...$permissions): void
    {
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        self::assertNotNull($user);

        $user->setPermissions(array_fill_keys($permissions, Grant::ALLOW));
        $this->entityManager->flush();
    }
}
