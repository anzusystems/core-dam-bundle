<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoShowEpisodeFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoShowFixtures;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\VideoShowEpisodeUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class VideoShowEpisodeControllerTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(VideoShowEpisodeUrl::getOne(VideoShowEpisodeFixtures::EPISODE_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $videoShowEpisodeResponse = $this->serializer->deserialize($response->getContent(), VideoShowEpisode::class);
        $videoShowEpisode = $this->entityManager->find(VideoShowEpisode::class, VideoShowEpisodeFixtures::EPISODE_1);

        $this->assertSame($videoShowEpisode->getId(), $videoShowEpisodeResponse->getId());
        $this->assertSame($videoShowEpisode->getTexts()->getTitle(), $videoShowEpisodeResponse->getTexts()->getTitle());
        $this->assertSame($videoShowEpisode->getLicence()->getId(), $videoShowEpisodeResponse->getLicence()->getId());
        $this->assertSame($videoShowEpisode->getAsset()->getId(), $videoShowEpisodeResponse->getAsset()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testPreparePayload(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);

        $response = $client->get(VideoShowEpisodeUrl::preparePayload(
            assetId: $video->getAsset()->getId(),
            videoShowId: VideoShowFixtures::SHOW_1
        ));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $videoShowEpisodeResponse = $this->serializer->deserialize($response->getContent(), VideoShowEpisode::class);

        $this->assertSame('Video title', $videoShowEpisodeResponse->getTexts()->getTitle());
        $this->assertSame($video->getAsset()->getId(), $videoShowEpisodeResponse->getAsset()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByShow(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $response = $client->get(VideoShowEpisodeUrl::getListByShow(VideoShowFixtures::SHOW_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $showList = $this->serializer->deserialize($response->getContent(), ApiInfiniteResponseList::class);

        $this->assertEquals(2, count($showList->getData()));
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByAsset(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);

        $response = $client->get(VideoShowEpisodeUrl::getListByAsset($video->getAsset()->getId()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $showList = $this->serializer->deserialize($response->getContent(), ApiInfiniteResponseList::class);

        $this->assertEquals(1, count($showList->getData()));
    }

    public function testCreateSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(VideoShowEpisodeUrl::createPath(), [
            'texts' => [
                'title' => 'Jozo',
            ],
            'videoShow' => VideoShowFixtures::SHOW_1
        ]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $videoShowEpisodeResponse = $this->serializer->deserialize($response->getContent(), VideoShowEpisode::class);

        $this->assertSame('Jozo', $videoShowEpisodeResponse->getTexts()->getTitle());
        $this->assertSame(VideoShowFixtures::SHOW_1, $videoShowEpisodeResponse->getVideoShow()->getId());
    }

    public function testCreateFailed(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(VideoShowEpisodeUrl::createPath(), []);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(
            json_decode($response->getContent(), true),
            [
                'videoShow' => [
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

        $response = $client->put(VideoShowEpisodeUrl::update(VideoShowEpisodeFixtures::EPISODE_1), [
            'id' => VideoShowEpisodeFixtures::EPISODE_1,
            'texts' => [
                'title' => 'Cokolom',
            ],
            'videoShow' => VideoShowFixtures::SHOW_1
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $videoShowEpisodeResponse = $this->serializer->deserialize($response->getContent(), VideoShowEpisode::class);

        $this->assertSame('Cokolom', $videoShowEpisodeResponse->getTexts()->getTitle());
        $this->assertSame(VideoShowFixtures::SHOW_1, $videoShowEpisodeResponse->getVideoShow()->getId());
    }
}
