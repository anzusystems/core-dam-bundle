<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoShowFixtures;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\VideoShowUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class VideoShowControllerTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(VideoShowUrl::getOne(VideoShowFixtures::SHOW_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $videoShowResponse = $this->serializer->deserialize($response->getContent(), VideoShow::class);
        $videoShow = $this->entityManager->find(VideoShow::class, VideoShowFixtures::SHOW_1);

        $this->assertSame($videoShow->getId(), $videoShowResponse->getId());
        $this->assertSame($videoShow->getTexts()->getTitle(), $videoShowResponse->getTexts()->getTitle());
        $this->assertSame($videoShow->getLicence()->getId(), $videoShowResponse->getLicence()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByExtSystem(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(VideoShowUrl::getListByExtSystem(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $showList = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($showList->getData()));
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByLicence(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(VideoShowUrl::getListByLicence(AssetLicenceFixtures::DEFAULT_LICENCE_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $showList = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($showList->getData()));
    }

    public function testCreateSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(VideoShowUrl::createPath(), [
            'texts' => [
                'title' => 'Jozo',
            ],
            'licence' => AssetLicenceFixtures::DEFAULT_LICENCE_ID
        ]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $videoShowResponse = $this->serializer->deserialize($response->getContent(), VideoShow::class);

        $this->assertSame('Jozo', $videoShowResponse->getTexts()->getTitle());
        $this->assertSame(AssetLicenceFixtures::DEFAULT_LICENCE_ID, $videoShowResponse->getLicence()->getId());
    }

    public function testCreateFailed(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(VideoShowUrl::createPath(), []);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(
            json_decode($response->getContent(), true),
            [
                'licence' => [
                    ValidationException::ERROR_FIELD_EMPTY,
                ],
                'texts.title' => [
                    ValidationException::ERROR_FIELD_EMPTY,
                ],
            ]
        );
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->put(VideoShowUrl::update(VideoShowFixtures::SHOW_1), [
            'id' => VideoShowFixtures::SHOW_1,
            'texts' => [
                'title' => 'Cokolom',
            ],
            'licence' => AssetLicenceFixtures::DEFAULT_LICENCE_ID
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $videoShowResponse = $this->serializer->deserialize($response->getContent(), VideoShow::class);

        $this->assertSame('Cokolom', $videoShowResponse->getTexts()->getTitle());
        $this->assertSame(AssetLicenceFixtures::DEFAULT_LICENCE_ID, $videoShowResponse->getLicence()->getId());
    }
}
