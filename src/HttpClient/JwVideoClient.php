<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoDtoFactory;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaGetDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoMediaUploadDto;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\JwVideoThumbnail;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\VideoUploadLinks;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\VideoUploadPayloadDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class JwVideoClient implements LoggerAwareInterface
{
    use SerializerAwareTrait;
    use LoggerAwareRequest;

    private const CHUNK_SIZE = 100 * 1_024 * 1_024;
    private const UPLOAD_TIMEOUT = 3_600;
    private const UPLOAD_DURATION = 3_600;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly HttpClientInterface $jwPlayerApiClient,
        private readonly JwVideoDtoFactory $jwVideoDtoFactory,
        private readonly DamLogger $damLogger,
    ) {
    }

    public function createThumbnail(JwDistributionServiceConfiguration $configuration, string $imageUrl, string $jwId): JwVideoThumbnail
    {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '_JwVideoDistribution_ create thumbnail',
            url: "/v2/sites/{$configuration->getSiteId()}/thumbnails/",
            method: Request::METHOD_POST,
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json: [
                'relationships' => [
                    'media' => [
                        [
                            'id' => $jwId,
                        ],
                    ],
                ],
                'upload' => [
                    'method' => 'fetch',
                    'thumbnail_type' => 'static',
                    'download_url' => $imageUrl,
                ],
            ]
        );

        if ($response->hasError()) {
            throw new RuntimeException('Create thumbnail failed');
        }

        return $this->serializer->deserialize($response->getContent(), JwVideoThumbnail::class);
    }

    /**
     * @throws JsonException
     * @throws SerializerException
     */
    public function getThumbnail(JwDistributionServiceConfiguration $configuration, string $thumbnailId): JwVideoThumbnail
    {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '_JwVideoDistribution_ get thumbnail',
            url: "/v2/sites/{$configuration->getSiteId()}/thumbnails/{$thumbnailId}",
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        );

        if ($response->hasError()) {
            throw new RuntimeException('Get thumbnail failed');
        }

        return $this->serializer->deserialize($response->getContent(), JwVideoThumbnail::class);
    }

    /**
     * @throws JsonException
     */
    public function setPoster(JwDistributionServiceConfiguration $configuration, string $thumbnailId): void
    {
        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '_JwVideoDistribution_ set poster',
            url: "/v2/sites/{$configuration->getSiteId()}/thumbnails/{$thumbnailId}",
            method: Request::METHOD_PATCH,
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json: [
                'relationships' => [
                    'media' => [
                        [
                            'is_poster' => true,
                        ],
                    ],
                ],
            ]
        );

        if ($response->hasError()) {
            throw new RuntimeException('Set poster failed');
        }
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    public function createVideoObject(
        JwDistributionServiceConfiguration $configuration,
        JwVideoMediaUploadDto $jwVideoDto,
    ): VideoUploadPayloadDto {
        $data = $this->serializer->toArray($jwVideoDto);
        $data['upload'] = ['method' => 'multipart'];

        $response = $this->loggedRequest(
            client: $this->jwPlayerApiClient,
            message: '_JwVideoDistribution_ create video object',
            url: "/v2/sites/{$configuration->getSiteId()}/media",
            method: Request::METHOD_POST,
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json: $data
        );

        if ($response->hasError()) {
            throw new RuntimeException('JwVideoDistribution create video failed');
        }

        return $this->serializer->deserialize($response->getContent(), VideoUploadPayloadDto::class);
    }

    /**
     * @throws SerializerException
     */
    public function uploadVideoObject(VideoUploadPayloadDto $dto, File $file): void
    {
        try {
            $partsCount = ceil(((int) $file->getSize()) / self::CHUNK_SIZE);

            $response = $this->client->request(
                Request::METHOD_GET,
                sprintf('https://api.jwplayer.com/v2/uploads/%s/parts?page=1&page_length=%d', $dto->getUploadId(), $partsCount),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $dto->getUploadToken(),
                        'Content-Type: application/json',
                    ],
                ]
            );
            $this->damLogger->info('_JwVideoDistribution_', 'Prepare multipart upload for upload (%s)', $dto->getUploadId());

            $listData = $this->serializer->deserialize($response->getContent(), VideoUploadLinks::class);

            $handle = fopen($file->getRealPath(), 'rb');

            foreach ($listData->getParts() as $part) {
                $chunk = fread($handle, self::CHUNK_SIZE);

                $response = $this->client->request(
                    Request::METHOD_PUT,
                    $part->getLink(),
                    [
                        'body' => $chunk,
                        'headers' => [
                            'Content-Length' => strlen($chunk),
                            'Content-Type' => '',
                        ],
                        'timeout' => self::UPLOAD_TIMEOUT,
                        'max_duration' => self::UPLOAD_DURATION,
                    ]
                );

                $response->getContent();
            }

            fclose($handle);

            $response = $this->client->request(
                Request::METHOD_PUT,
                sprintf('https://api.jwplayer.com/v2/uploads/%s/complete', $dto->getUploadId()),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $dto->getUploadToken(),
                        'Content-Type: application/json',
                    ],
                ]
            );

            $response->getContent();

            return;
        } catch (Throwable $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('JwVideo failed upload video (%s)', $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
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
            message: '_JwVideoDistribution_ get video object',
            url: "/v2/sites/{$configuration->getSiteId()}/media/{$distribution->getExtId()}/",
            headers: [
                'Authorization' => "Bearer {$configuration->getSecretV2()}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        );

        if ($response->hasError()) {
            throw new RuntimeException('JwVideoDistribution get video failed');
        }

        return $this->serializer->deserialize($response->getContent(), JwVideoMediaGetDto::class);
    }
}
