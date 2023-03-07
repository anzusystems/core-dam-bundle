<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\KeywordFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\KeywordUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class JwDistributionControllerTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $payload = [
            'distributionService' => 'jw_cms_main',
            'texts' => [
                'title' => 'Title',
                'description' => 'Description',
                'author' => 'Author',
                'keywords' => ['keyword'],
            ]
        ];


        $response = $client->post(
            sprintf('/api/adm/v1/jw-distribution/asset-file/%s/distribute', VideoFixtures::VIDEO_ID_1),
            $payload
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $jwDistribution = $this->serializer->deserialize($response->getContent(), JwDistribution::class);

        $this->assertSame($payload['texts']['title'], $jwDistribution->getTexts()->getTitle());
        $this->assertSame($payload['texts']['description'], $jwDistribution->getTexts()->getDescription());
        $this->assertSame($payload['texts']['author'], $jwDistribution->getTexts()->getAuthor());
        $this->assertSame($payload['texts']['keywords'], $jwDistribution->getTexts()->getKeywords());

        $this->assertSame($payload['distributionService'], $jwDistribution->getDistributionService());
        $this->assertSame(DistributionProcessStatus::Distributed, $jwDistribution->getStatus());
        $this->assertSame([
            'thumbnail' => [
                'type' => 'url',
                'value' => 'https://cdn.jwplayer.com/v2/media/123jw/poster.jpg'
            ]
        ], $jwDistribution->getDistributionData());
    }
}
