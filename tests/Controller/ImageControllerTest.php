<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures as TestImageFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class ImageControllerTest extends AbstractApiController
{
    #[DataProvider('getDataProvider')]
    public function testGet(string $url, int $statusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $response = $client->get($url);

        $this->assertSame($response->getStatusCode(), $statusCode);
    }

    public static function getDataProvider(): array
    {
        return [
            [
                'http://image.anzusystems.localhost/image/w800-h450-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_OK
            ],
            [
                'http://image.anzusystems.localhost/image/w200-h100-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
            [
                'http://admin-image.anzusystems.localhost/image/w800-h450-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
            [
                'http://admin-image.anzusystems.localhost/image/w200-h100-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_OK
            ],
            [
                'http://admin-image.anzusystems.localhost/image/w300-h300-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
            [
                'http://admin-image.anzusystems.localhost/image/w300-h300-c0/'.ImageFixtures::IMAGE_ID_1_1.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
            [
                'http://image.anzusystems.localhost/image/w300-h300-c0/'. TestImageFixtures::IMAGE_ID_3.'.jpg',
                Response::HTTP_OK
            ],
            [
                'http://admin-image.anzusystems.localhost/image/w300-h300-c0/'. TestImageFixtures::IMAGE_ID_3.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
            [
                'http://image.anzusystems.localhost/image/w800-h450-c0/'. TestImageFixtures::IMAGE_ID_3.'.jpg',
                Response::HTTP_BAD_REQUEST
            ],
        ];
    }
}
