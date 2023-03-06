<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures as BaseImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\PodcastUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class PodcastControllerTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(PodcastUrl::getOne(PodcastFixtures::PODCAST_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = $this->serializer->deserialize($response->getContent(), Podcast::class);
        $entity = $this->entityManager->find(Podcast::class, PodcastFixtures::PODCAST_1);

        $this->assertSame($entity->getId(), $response->getId());
        $this->assertSame($entity->getTexts()->getTitle(), $response->getTexts()->getTitle());
        $this->assertSame($entity->getLicence()->getId(), $response->getLicence()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListByExtSystem(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(PodcastUrl::getListByExtSystem(ExtSystemFixtures::ID_CMS));
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

        $response = $client->get(PodcastUrl::getListByLicence(BaseLicenceFixtures::DEFAULT_LICENCE_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $showList = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($showList->getData()));
    }

    /**
     * @dataProvider podcastPayloadDataProvider
     */
    public function testCreateSuccess(array $payload): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(PodcastUrl::createPath(), $payload);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $this->assertSamePodcast(
            expectedPayload: $payload,
            newPodcast: $this->serializer->deserialize($response->getContent(), Podcast::class),
        );
    }



    public function testCreateFailed(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(PodcastUrl::createPath(), [
            'attributes' => [
                'rssUrl' => 'nono',
                'fileSlot' => 'unknown',
            ],
            'licence' => BaseLicenceFixtures::DEFAULT_LICENCE_ID,
            'imagePreview' => [
                'imageFile' => ImageFixtures::IMAGE_ID_1
            ],
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(
            json_decode($response->getContent(), true),
            [
                'imagePreview' => [
                    'error_invalid_licence',
                ],
                'attributes.rssUrl' => [
                    ValidationException::ERROR_FIELD_INVALID,
                ],
                'attributes.fileSlot' => [
                    ValidationException::ERROR_FIELD_INVALID,
                ],
                'texts.title' => [
                    ValidationException::ERROR_FIELD_EMPTY,
                ],
            ]
        );
    }

    /**
     * @dataProvider podcastPayloadDataProvider
     */
    public function testUpdateSuccess(array $payload): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $payload['id'] = PodcastFixtures::PODCAST_1;
        $response = $client->put(PodcastUrl::update(PodcastFixtures::PODCAST_1), $payload);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->assertSamePodcast(
            expectedPayload: $payload,
            newPodcast: $this->serializer->deserialize($response->getContent(), Podcast::class),
        );


    }

    private function assertSamePodcast(array $expectedPayload, Podcast $newPodcast): void
    {
        $this->assertSame($expectedPayload['texts']['title'], $newPodcast->getTexts()->getTitle());
        $this->assertSame($expectedPayload['texts']['description'], $newPodcast->getTexts()->getDescription());
        $this->assertSame($expectedPayload['attributes']['rssUrl'], $newPodcast->getAttributes()->getRssUrl());
        $this->assertSame($expectedPayload['attributes']['fileSlot'], $newPodcast->getAttributes()->getFileSlot());
        $this->assertSame($expectedPayload['attributes']['mode'], $newPodcast->getAttributes()->getMode()->toString());
        $this->assertSame($expectedPayload['imagePreview']['imageFile'], (string) $newPodcast->getImagePreview()->getImageFile()->getId());
        $this->assertSame(BaseLicenceFixtures::DEFAULT_LICENCE_ID, $newPodcast->getLicence()->getId());
    }

    public function podcastPayloadDataProvider(): array
    {
        return [
            [
                [
                    'texts' => [
                        'title' => 'title',
                        'description' => 'Description',
                    ],
                    'attributes' => [
                        'rssUrl' => 'http://test.url.test',
                        'fileSlot' => 'paid',
                        'mode' => PodcastImportMode::NotImport->toString()
                    ],
                    'imagePreview' => [
                        'imageFile' => BaseImageFixtures::IMAGE_ID_1_1
                    ],
                    'licence' => BaseLicenceFixtures::DEFAULT_LICENCE_ID
                ]
            ]
        ];
    }
}
