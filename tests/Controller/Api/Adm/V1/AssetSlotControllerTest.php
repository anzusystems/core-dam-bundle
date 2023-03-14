<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetSlotUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class AssetSlotControllerTest extends AbstractApiControllerTest
{
    private readonly AudioFileRepository $audioFileRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audioFileRepository = $this->getService(AudioFileRepository::class);
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);


        $audioFile = $this->audioFileRepository->find(AudioFixtures::AUDIO_ID_1);
        $response = $client->get(AssetSlotUrl::getList($audioFile->getAsset()->getId()));

        $list = $this->serializer->deserialize($response->getContent(), ApiResponseList::class);
        $this->assertCount(1, $list->getData());

        $this->validateSlots(
            [
                [
                    'slotName' => 'free',
                    'assetFile' => AudioFixtures::AUDIO_ID_1
                ]
            ],
            $list
        );
    }

    /**
     * @dataProvider updateSlotDataProvider
     *
     * @throws SerializerException
     */
    public function testUpdateSlot(array $reqJson): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $audioFile = $this->audioFileRepository->find(AudioFixtures::AUDIO_ID_1);

        $response = $client->patch(
            uri: AssetSlotUrl::update($audioFile->getAsset()->getId()),
            jsonBody: $reqJson
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $list = $this->serializer->deserialize($response->getContent(), ApiResponseList::class);
        $this->validateSlots($reqJson, $list);
    }

    /**
     * @dataProvider updateSlotFailedProvider
     */
    public function testUpdateSlotFailed(array $reqJson): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $audioFile = $this->audioFileRepository->find(AudioFixtures::AUDIO_ID_1);

        $response = $client->patch(
            uri: AssetSlotUrl::update($audioFile->getAsset()->getId()),
            jsonBody: $reqJson
        );

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function updateSlotFailedProvider(): array
    {
        return [
            [
                'reqJson' => [
                    [
                        'slotName' => 'free',
                        'assetFile' => AudioFixtures::AUDIO_ID_1
                    ],
                    [
                        'slotName' => 'paid',
                        'assetFile' => AudioFixtures::AUDIO_ID_2
                    ]
                ],
            ],
            [
                'reqJson' => []
            ]
        ];
    }

    private function updateSlotDataProvider(): array
    {
        return [
            [
                'reqJson' => [
                    [
                        'slotName' => 'free',
                        'assetFile' => AudioFixtures::AUDIO_ID_1
                    ]
                ],
            ],
            [
                'reqJson' => [
                    [
                        'slotName' => 'paid',
                        'assetFile' => AudioFixtures::AUDIO_ID_1
                    ]
                ],
            ],
            [
                'reqJson' => [
                    [
                        'slotName' => 'free',
                        'assetFile' => AudioFixtures::AUDIO_ID_1
                    ],
                    [
                        'slotName' => 'paid',
                        'assetFile' => AudioFixtures::AUDIO_ID_1
                    ]
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{
     *     slotName: string,
     *     assetFile: string
     * }> $reqJson
     */
    private function validateSlots(array $reqJson, ApiResponseList $list): void {
        /** @var array{
         *     slotName: string,
         *     assetFile: array {id: string}
         * } $slotDto
         */
        foreach ($list->getData() as $slotDto) {
            $expectedJsonItem = null;
            foreach ($reqJson as $reqJsonItem) {
                if (
                    $reqJsonItem['assetFile'] === $slotDto['assetFile']['id'] &&
                    $reqJsonItem['slotName'] === $slotDto['slotName']
                ) {
                    $expectedJsonItem = $reqJsonItem;
                    break;
                }
            }

            $this->assertNotNull($expectedJsonItem);
        }

        $this->assertCount(count($reqJson), $list->getData());
    }
}
