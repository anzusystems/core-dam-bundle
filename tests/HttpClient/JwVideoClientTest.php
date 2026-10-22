<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\JwVideo\VideoUploadPayloadDto;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JwVideoClientTest extends CoreDamKernelTestCase
{
    private const int PART_SIZE = 100 * 1_024 * 1_024;
    private const int FILE_SIZE = 250 * 1_024 * 1_024;

    private string $path;

    /**
     * @var list<array{int, int, string}>
     */
    private array $uploadedParts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/jw_video_client_test_' . uniqid();
        $handle = fopen($this->path, 'wb');
        self::assertIsResource($handle);
        ftruncate($handle, self::FILE_SIZE);
        foreach (['A', 'B', 'C'] as $index => $marker) {
            fseek($handle, $index * self::PART_SIZE);
            fwrite($handle, $marker);
        }
        fclose($handle);
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        parent::tearDown();
    }

    public function testVideoIsUploadedInPartsStreamedFromTheFile(): void
    {
        $dto = (new VideoUploadPayloadDto())
            ->setId('jw-id')
            ->setUploadToken('jw-upload-token')
            ->setUploadId('jw-upload-id');

        $this->buildClient()->uploadVideoObject($dto, new File($this->path));

        self::assertSame(
            [
                [self::PART_SIZE, self::PART_SIZE, 'A'],
                [self::PART_SIZE, self::PART_SIZE, 'B'],
                [self::FILE_SIZE - 2 * self::PART_SIZE, self::FILE_SIZE - 2 * self::PART_SIZE, 'C'],
            ],
            $this->uploadedParts
        );
    }

    public function testMediaUnknownToDeliveryApiIsWaitedForWithoutError(): void
    {
        $journalLogger = $this->createMock(LoggerInterface::class);
        $journalLogger->expects(self::never())->method('error');
        $client = $this->buildClient(
            new MockHttpClient(new MockResponse('{"message":"Invalid media id"}', ['http_code' => Response::HTTP_NOT_FOUND]))
        );
        $client->setJournalLogger($journalLogger);
        $client->setContextFactory($this->getService(LogContextFactory::class));

        $this->expectException(RemoteProcessingWaitingException::class);

        $client->getVideoAssetData(JwDistributionServiceConfiguration::getFromArrayConfiguration([]), 'jw-id');
    }

    private function buildClient(?HttpClientInterface $cdnClient = null): JwVideoClient
    {
        $client = new JwVideoClient(
            new MockHttpClient(fn (string $method, string $url, array $options): MockResponse => $this->respond($url, $options)),
            $this->createStub(HttpClientInterface::class),
            $cdnClient ?? $this->createStub(HttpClientInterface::class),
            $this->getService(DamLogger::class),
            $this->createStub(LoggerInterface::class),
        );
        $client->setSerializer($this->getService(Serializer::class));

        return $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function respond(string $url, array $options): MockResponse
    {
        if (str_contains($url, '/parts?')) {
            return new MockResponse((string) json_encode(['parts' => [
                ['upload_link' => 'https://upload.jw/part-1'],
                ['upload_link' => 'https://upload.jw/part-2'],
                ['upload_link' => 'https://upload.jw/part-3'],
            ]]));
        }
        if (str_contains($url, 'upload.jw/part-')) {
            $this->uploadedParts[] = $this->readPart($options);
        }

        return new MockResponse('');
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{int, int, string}
     */
    private function readPart(array $options): array
    {
        $body = $options['body'];
        self::assertInstanceOf(Closure::class, $body);
        $contentLength = (int) substr($options['normalized_headers']['content-length'][0], strlen('Content-Length: '));

        $firstByte = $body(1);
        $length = strlen($firstByte);
        while (App::EMPTY_STRING !== $data = $body(1_024 * 1_024)) {
            $length += strlen($data);
        }

        return [$contentLength, $length, $firstByte];
    }
}
