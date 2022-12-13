<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategorySelectRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\DistributionCategorySelectUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class DistributionCategorySelectTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $distributionCategorySelectRepository = self::getContainer()->get(DistributionCategorySelectRepository::class);
        $fromDb = $distributionCategorySelectRepository->findOneBy([]);

        $response = $client->get(DistributionCategorySelectUrl::getOne($fromDb->getId()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $distributionCategorySelect = $this->serializer->deserialize(
            $response->getContent(),
            DistributionCategorySelect::class
        );

        $this->assertSame($fromDb->getId(), $distributionCategorySelect->getId());
        $this->assertSame($fromDb->getType(), $distributionCategorySelect->getType());
        $this->assertSame($fromDb->getExtSystem()->getId(), $distributionCategorySelect->getExtSystem()->getId());
        $this->assertSame($fromDb->getServiceSlug(), $distributionCategorySelect->getServiceSlug());
        $this->assertEmpty($distributionCategorySelect->getOptions());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(DistributionCategorySelectUrl::getList(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $distributionCategorySelect = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertSame(1, count($distributionCategorySelect->getData()));
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $distributionCategorySelectRepository = self::getContainer()->get(DistributionCategorySelectRepository::class);
        $fromDb = $distributionCategorySelectRepository->findOneBy([]);

        // Test add options
        $response = $client->put(DistributionCategorySelectUrl::update($fromDb->getId()), [
            'id' => $fromDb->getId(),
            'serviceSlug' => $fromDb->getServiceSlug(),
            'extSystem' => $fromDb->getExtSystem()->getId(),
            'type' => $fromDb->getType(),
            'options' => [
                [
                    'id' => null,
                    'select' => $fromDb->getId(),
                    'name' => 'Option 1',
                    'value' => '1',
                    'assignable' => true,
                    'position' => 3,
                ],
                [
                    'id' => null,
                    'select' => $fromDb->getId(),
                    'name' => 'Option 2',
                    'value' => '2',
                    'assignable' => false,
                    'position' => 1,
                ]
            ],
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $distributionCategorySelect = $this->serializer->deserialize(
            $response->getContent(),
            DistributionCategorySelect::class
        );

        $this->assertSame($fromDb->getId(), $distributionCategorySelect->getId());
        $this->assertSame($fromDb->getType(), $distributionCategorySelect->getType());
        $this->assertSame($fromDb->getExtSystem()->getId(), $distributionCategorySelect->getExtSystem()->getId());
        $this->assertSame($fromDb->getServiceSlug(), $distributionCategorySelect->getServiceSlug());
        $this->assertCount(2, $distributionCategorySelect->getOptions());

        // check correct reorder
        $firstOption = $distributionCategorySelect->getOptions()[0];
        $secondOption = $distributionCategorySelect->getOptions()[1];
        $this->assertSame('Option 2', $firstOption->getName());
        $this->assertSame('Option 1', $secondOption->getName());
        $this->assertSame(1, $firstOption->getPosition());
        $this->assertSame(2, $secondOption->getPosition());

        // Test remove option and update one
        $response = $client->put(DistributionCategorySelectUrl::update($fromDb->getId()), [
            'id' => $fromDb->getId(),
            'serviceSlug' => $fromDb->getServiceSlug(),
            'extSystem' => $fromDb->getExtSystem()->getId(),
            'type' => $fromDb->getType(),
            'options' => [
                [
                    'id' => $secondOption->getId(),
                    'select' => $secondOption->getSelect()->getId(),
                    'name' => $secondOption->getName(),
                    'value' => $secondOption->getValue(),
                    'assignable' => $secondOption->isAssignable(),
                    'position' => 1,
                ]
            ],
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $distributionCategorySelect = $this->serializer->deserialize(
            $response->getContent(),
            DistributionCategorySelect::class
        );

        $this->assertSame($fromDb->getId(), $distributionCategorySelect->getId());
        $this->assertSame($fromDb->getType(), $distributionCategorySelect->getType());
        $this->assertSame($fromDb->getExtSystem()->getId(), $distributionCategorySelect->getExtSystem()->getId());
        $this->assertSame($fromDb->getServiceSlug(), $distributionCategorySelect->getServiceSlug());
        $this->assertCount(1, $distributionCategorySelect->getOptions());
        $this->assertSame($secondOption->getId(), $distributionCategorySelect->getOptions()[0]->getId());
    }
}
