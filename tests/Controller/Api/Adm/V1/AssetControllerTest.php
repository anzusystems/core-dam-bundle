<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetControllerTest extends AbstractApiController
{
    private const int AUTO_DELETE_DAYS = 30;

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

    #[DataProvider('autoDeleteAtDataProvider')]
    public function testGetOneShowsWhenTheLicenceDeletesTheAsset(bool $autoDeleteActive, ?string $expectedDeleteAfter): void
    {
        $asset = $this->setAutoDeleteOnTheFixturePhotoLicence($autoDeleteActive);
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetUrl::getOne((string) $asset->getId()));

        self::assertStatusCode($response, Response::HTTP_OK);
        $autoDeleteAt = json_decode($response->getContent(), true)['autoDeleteAt'];
        self::assertSame(
            null === $expectedDeleteAfter ? null : $asset->getCreatedAt()->modify($expectedDeleteAfter)->getTimestamp(),
            null === $autoDeleteAt ? null : new DateTimeImmutable($autoDeleteAt)->getTimestamp(),
        );
    }

    public static function autoDeleteAtDataProvider(): iterable
    {
        yield 'licence deleting its assets after 30 days' => [true, sprintf('+%d days', self::AUTO_DELETE_DAYS)];
        yield 'licence keeping its assets' => [false, null];
    }

    private function setAutoDeleteOnTheFixturePhotoLicence(bool $active): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $image);
        $image->getLicence()->getAutoDelete()->setActive($active)->setOlderThanDays(self::AUTO_DELETE_DAYS);
        $this->entityManager->flush();

        return $image->getAsset();
    }
}
