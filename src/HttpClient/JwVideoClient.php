<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaGetDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaUploadDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\VideoUploadPayloadDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JwVideoClient implements LoggerAwareInterface
{
    use SerializerAwareTrait;
    use LoggerAwareRequest;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly HttpClientInterface $jwPlayerApiClient,
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    public function createVideoObject(
        JwDistributionServiceConfiguration $configuration,
        JwVideoMediaUploadDto $jwVideoDto,
    ): VideoUploadPayloadDto {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '[JwVideoDistribution] create video object',
            url: "/v2/sites/{$configuration->getSiteId()}/media",
            method: Request::METHOD_POST,
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json: $this->serializer->toArray($jwVideoDto)
        );

        if ($response->hasError()) {
            throw new RuntimeException('JwVideoDistribution create video failed');
        }

        return $this->serializer->deserialize($response->getContent(), VideoUploadPayloadDto::class);
    }

    /**
     * @throws JsonException
     */
    public function uploadVideoObject(VideoUploadPayloadDto $videoUploadPayloadDto, File $file): void
    {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '[JwVideoDistribution] upload video object',
            url: $videoUploadPayloadDto->getUploadLink(),
            method: Request::METHOD_PUT,
            headers: [
                'headers' => [
                    'Content-Type' => '',
                ],
            ],
            body: file_get_contents($file->getRealPath())
        );

        if ($response->hasError()) {
            throw new RuntimeException('JwVideoDistribution upload video failed');
        }
    }

    /**
     * @throws JsonException
     * @throws SerializerException
     */
    public function getVideoObject(JwDistributionServiceConfiguration $configuration, JwDistribution $distribution): JwVideoMediaGetDto
    {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '[JwVideoDistribution] get video object',
            url: "/v2/sites/{$configuration->getSiteId()}/media/{$distribution->getExtId()}/",
            headers: [
                'headers' => [
                    'Content-Type' => '',
                ],
            ],
        );

        if ($response->hasError()) {
            throw new RuntimeException('JwVideoDistribution get video failed');
        }

        return $this->serializer->deserialize($response->getContent(), JwVideoMediaGetDto::class);
    }
}
