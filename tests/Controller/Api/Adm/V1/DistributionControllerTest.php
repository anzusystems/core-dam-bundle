<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\DistributionUrl;
use Symfony\Component\HttpFoundation\Response;

final class DistributionControllerTest extends AbstractApiController
{
    public function testSearchRejectsOversizedLicenceCollection(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(DistributionUrl::search(range(1, AssetLicence::COLLECTION_MAX + 1)));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'licences' => [ValidationException::ERROR_FIELD_RANGE_MAX],
        ]);
    }

    public function testSearchRejectsLicencesFromDifferentExtSystems(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(DistributionUrl::search([
            AssetLicenceFixtures::LICENCE_ID,
            AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
        ]));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'licences' => [ValidationException::ERROR_FIELD_INVALID],
        ]);
    }
}
