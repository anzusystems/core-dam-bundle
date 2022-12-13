<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Pub;

use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ConfigurationUrl;
use Symfony\Component\HttpFoundation\Response;
use function Symfony\Component\String\u;

final class ConfigurationControllerTest extends AbstractApiControllerTest
{
    public function testGet(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(ConfigurationUrl::getPub());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $config = json_decode($response->getContent(), true);
        $this->assertArrayHasKey(u(SettingsConfiguration::USER_AUTH_TYPE_KEY)->camel()->toString(), $config);
    }
}
