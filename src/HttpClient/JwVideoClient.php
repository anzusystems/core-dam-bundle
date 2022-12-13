<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaGetDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaUploadDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\VideoUploadPayloadDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class JwVideoClient
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly HttpClientInterface $jwPlayerApiClient,
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
        private readonly DamLogger $logger
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function createVideoObject(JwDistributionServiceConfiguration $configuration, JwVideoMediaUploadDto $jwVideoDto)
    {
        try {
            $response = $this->jwPlayerApiClient->request(
                Request::METHOD_POST,
                "/v2/sites/{$configuration->getSiteId()}/media",
                [
                    'body' => $this->serializer->serialize($jwVideoDto),
                    'headers' => [
                        'Authorization' => "Bearer {$configuration->getSecretV2()}",
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]
            );

            return $this->serializer->deserialize($response->getContent(), VideoUploadPayloadDto::class);
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('JwVideo failed create video (%s)', $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @throws SerializerException
     */
    public function uploadVideoObject(VideoUploadPayloadDto $videoUploadPayloadDto, File $file): void
    {
        try {
            $response = $this->client->request(
                Request::METHOD_PUT,
                $videoUploadPayloadDto->getUploadLink(),
                [
                    'body' => file_get_contents($file->getRealPath()),
                    'headers' => [
                        'Content-Type' => '',
                    ],
                ]
            );

            $response->getContent();

            return;
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('JwVideo failed upload video (%s)', $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @throws SerializerException
     */
    public function getVideoObject(JwDistributionServiceConfiguration $configuration, JwDistribution $distribution): JwVideoMediaGetDto
    {
        try {
            $response = $this->jwPlayerApiClient->request(
                Request::METHOD_GET,
                "/v2/sites/{$configuration->getSiteId()}/media/{$distribution->getExtId()}/",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$configuration->getSecretV2()}",
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            return $this->serializer->deserialize($response->getContent(), JwVideoMediaGetDto::class);
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('JwVideo failed fetch video detail (%s)', $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }
}
