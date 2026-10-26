<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Distribution\Modules;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwPlayerDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwDirectSourceUrlProvider;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwPlayerCustomDataFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoThumbnail;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JwPlayerDistributionModuleTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';

    public function testReadyVideoUnknownToDeliveryApiFailsAfterRemoteProcessingLimit(): void
    {
        $this->expectException(RemoteProcessingFailedException::class);

        $this->buildModule(new MockResponse('{"message":"Invalid media id"}', ['http_code' => Response::HTTP_NOT_FOUND]))
            ->checkDistributionStatus($this->createDistribution(App::getAppDate()->modify('-4 hours')));
    }

    public function testReadyVideoUnknownToDeliveryApiKeepsWaitingWithinRemoteProcessingLimit(): void
    {
        $this->expectException(RemoteProcessingWaitingException::class);

        $this->buildModule(new MockResponse('{"message":"Invalid media id"}', ['http_code' => Response::HTTP_NOT_FOUND]))
            ->checkDistributionStatus($this->createDistribution(App::getAppDate()->modify('-2 hours')));
    }

    public function testReadyVideoKnownToDeliveryApiIsDistributedAfterRemoteProcessingLimit(): void
    {
        $distribution = $this->createDistribution(App::getAppDate()->modify('-4 hours'));

        $this->buildModule(new MockResponse('{"playlist":[]}'))->checkDistributionStatus($distribution);

        self::assertNotEmpty($distribution->getDistributionData());
    }

    private function createDistribution(DateTimeImmutable $modifiedAt): JwDistribution
    {
        $distribution = new JwDistribution();
        $distribution->setDistributionService(self::DISTRIBUTION_SERVICE);
        $distribution->setExtId('jw-id');
        $distribution->setModifiedAt($modifiedAt);

        return $distribution;
    }

    private function buildModule(MockResponse $deliveryApiResponse): JwPlayerDistributionModule
    {
        $client = new JwVideoClient(
            $this->createStub(HttpClientInterface::class),
            new MockHttpClient(new MockResponse('{"status":"ready"}')),
            new MockHttpClient($deliveryApiResponse),
            $this->getService(DamLogger::class),
            $this->createStub(LoggerInterface::class),
        );
        $client->setSerializer($this->getService(Serializer::class));
        $client->setJournalLogger($this->createStub(LoggerInterface::class));
        $client->setContextFactory($this->getService(LogContextFactory::class));

        $module = new JwPlayerDistributionModule(
            $client,
            $this->getService(JwVideoDtoFactory::class),
            $this->getService(JwPlayerCustomDataFactory::class),
            $this->getService(JwVideoThumbnail::class),
            $this->getService(JwVideoImagePreviewFactory::class),
            new JwDirectSourceUrlProvider($client),
        );
        $module->setDistributionConfigurationProvider($this->getService(DistributionConfigurationProvider::class));

        return $module;
    }
}
