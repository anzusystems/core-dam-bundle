<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
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

        self::assertStatusCode($response, Response::HTTP_OK);
    }

    public function testLicenceSearchRejectsOversizedCollectionBeforeQuerying(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetUrl::licenceSearch(range(1, AssetLicence::COLLECTION_MAX + 1)));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'licences' => [ValidationException::ERROR_FIELD_RANGE_MAX],
        ]);
    }

    public function testLicenceSearchRejectsLicencesFromDifferentExtSystems(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetUrl::licenceSearch([
            AssetLicenceFixtures::LICENCE_ID,
            AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
        ]));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, [
            'licences' => [ValidationException::ERROR_FIELD_INVALID],
        ]);
    }
}
