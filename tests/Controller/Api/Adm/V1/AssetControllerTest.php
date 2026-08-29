<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use Symfony\Component\HttpFoundation\Response;

final class AssetControllerTest extends AbstractApiController
{
    /**
     * Regression: LicenceCollectionHandler used to call EntityIdHandler::deserialize() outside the
     * deserializer's batch, which silently dropped every id absent from the identity map - the
     * collection ended up narrower than requested instead of failing loudly.
     */
    public function testLicenceSearchReturnsResultsForEveryRequestedLicence(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetUrl::licenceSearch([
            AssetLicenceFixtures::LICENCE_ID,
            AssetLicenceFixtures::LICENCE_2_ID,
        ]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testLicenceSearchRejectsLicencesFromDifferentExtSystems(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetUrl::licenceSearch([
            AssetLicenceFixtures::LICENCE_ID,
            AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
        ]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, [
            'licences' => [ValidationException::ERROR_FIELD_INVALID],
        ]);
    }
}
