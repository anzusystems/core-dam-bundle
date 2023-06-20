<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures as BaseImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastEpisodeFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\PodcastEpisodeUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class PodcastEpisodeControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(PodcastEpisodeUrl::getOne(PodcastEpisodeFixtures::EPISODE_1_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = $this->serializer->deserialize($response->getContent(), PodcastEpisode::class);
        $entity = $this->entityManager->find(PodcastEpisode::class, PodcastEpisodeFixtures::EPISODE_1_ID);

        $this->assertSame($entity->getId(), $response->getId());
        $this->assertSame($entity->getTexts()->getTitle(), $response->getTexts()->getTitle());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByPodcast(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(PodcastEpisodeUrl::getListByPodcast(PodcastFixtures::PODCAST_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $list = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($list->getData()));
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByLicence(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $audio = $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_1);

        $response = $client->get(PodcastEpisodeUrl::getListByAsset((string) $audio->getAsset()->getId()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $showList = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($showList->getData()));
    }

    /**
     * @dataProvider podcastEpisodePayloadDataProvider
     * @throws SerializerException
     */
    public function testCreateSuccess(array $payload): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(PodcastEpisodeUrl::createPath(), $payload);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $this->assertSamePodcast(
            expectedPayload: $payload,
            newPodcast: $this->serializer->deserialize($response->getContent(), PodcastEpisode::class),
        );
    }

    /**
     * @dataProvider createFailedDataProvider
     */
    public function testCreateFailed(array $payload, array $validationErrors, ?string $assetFileId = null): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        if ($assetFileId) {
            $assetFile = $this->entityManager->find(AssetFile::class,$assetFileId);
            $payload['asset'] = $assetFile->getAsset()->getId();
        }

        $response = $client->post(PodcastEpisodeUrl::createPath(), $payload);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(
            json_decode($response->getContent(), true),
            $validationErrors
        );
    }

    private function createFailedDataProvider(): array
    {
        return [
            [
                [],
                [
                    'podcast' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ],
                    'texts.title' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ],
                ],
                null
            ],
            [
                [
                    'texts' => [
                        'title' => 'title',
                    ],
                    'podcast' => PodcastFixtures::PODCAST_1,
                ],
                [
                    'asset' => [
                        ValidationException::ERROR_FIELD_INVALID,
                        'error_invalid_licence'
                    ],
                ],
                ImageFixtures::IMAGE_ID_1
            ]
        ];
    }

    /**
     * @dataProvider podcastEpisodePayloadDataProvider
     * @throws SerializerException
     */
    public function testUpdateSuccess(array $payload): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $payload['id'] = PodcastEpisodeFixtures::EPISODE_1_ID;
        $response = $client->put(PodcastEpisodeUrl::update(PodcastEpisodeFixtures::EPISODE_1_ID), $payload);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->assertSamePodcast(
            expectedPayload: $payload,
            newPodcast: $this->serializer->deserialize($response->getContent(), PodcastEpisode::class),
        );
    }

    private function assertSamePodcast(array $expectedPayload, PodcastEpisode $newPodcast): void
    {
        $this->assertSame($expectedPayload['texts']['title'], $newPodcast->getTexts()->getTitle());
        $this->assertSame($expectedPayload['texts']['description'], $newPodcast->getTexts()->getDescription());
        $this->assertSame($expectedPayload['attributes']['seasonNumber'], $newPodcast->getAttributes()->getSeasonNumber());
        $this->assertSame($expectedPayload['attributes']['episodeNumber'], $newPodcast->getAttributes()->getEpisodeNumber());
        $this->assertSame($expectedPayload['dates']['publicationDate'], $newPodcast->getDates()->getPublicationDate()->format(App::DATE_TIME_API_FORMAT));
        $this->assertSame($expectedPayload['podcast'], (string) $newPodcast->getPodcast()->getId());
        $this->assertSame($expectedPayload['imagePreview']['imageFile'], (string) $newPodcast->getImagePreview()->getImageFile()->getId());
    }

    public function podcastEpisodePayloadDataProvider(): array
    {
        return [
            [
                [
                    'texts' => [
                        'title' => 'title',
                        'description' => 'Description',
                    ],
                    'attributes' => [
                        'seasonNumber' => 1,
                        'episodeNumber' => 2,
                    ],
                    'dates' => [
                        'publicationDate'=> App::getAppDate()->format(App::DATE_TIME_API_FORMAT),
                    ],
                    'imagePreview' => [
                        'imageFile' => BaseImageFixtures::IMAGE_ID_1_1
                    ],
                    'podcast' => PodcastFixtures::PODCAST_1
                ]
            ]
        ];
    }
}
