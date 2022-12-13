<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AssetUrlInterface;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAssetFileApiControllerTest extends AbstractApiControllerTest
{
    protected FileSystemProvider $filesystemProvider;
    protected NameGenerator $nameGenerator;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var FileSystemProvider $filesystemProvider */
        $filesystemProvider = static::getContainer()->get(FileSystemProvider::class);
        $this->filesystemProvider = $filesystemProvider;

        /** @var NameGenerator $nameGenerator */
        $nameGenerator = static::getContainer()->get(NameGenerator::class);
        $this->nameGenerator = $nameGenerator;
    }

    public function getFile(string $filePath): UploadedFile
    {
        $file = new File(ImageFixtures::DATA_PATH . $filePath);

        return new UploadedFile($file->getRealPath(), $file->getFilename());
    }

    public function addToPosition(
        ApiClient $apiClient,
        AssetUrlInterface $assetUrl,
        UploadedFile $file,
        string $assetId,
        string $position,
        int $expectedStatusCode,
        ?array $body = null,
    ): Response {
        $checksum = FileHelper::checksumFromPath((string)$file->getRealPath());
        $response = $apiClient->post(
            $assetUrl->getAddToPositionPath($assetId, $position),
            $body ?: [
                'mimeType' => $file->getMimeType(),
                'checksum' => $checksum,
                'size' => $file->getSize()
            ]
        );

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            sprintf(
                'Asset create at position (%s) failed. Api request status code (%s) and content (%s)',
                $position,
                $response->getStatusCode(),
                $response->getContent()
            )
        );

        return $response;
    }

    public function createAsset(
        ApiClient $apiClient,
        AssetUrlInterface $assetUrl,
        UploadedFile $file,
        int $expectedStatusCode,
        ?array $body = null,
    ): Response {
        $checksum = FileHelper::checksumFromPath((string)$file->getRealPath());
        $response = $apiClient->post(
            $assetUrl->getCreatePath(),
            $body ?: [
                'mimeType' => $file->getMimeType(),
                'checksum' => $checksum,
                'size' => $file->getSize()
            ]
        );

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            sprintf(
                'Asset create failed. Api request status code (%s) and content (%s)',
                $response->getStatusCode(),
                $response->getContent()
            )
        );

        return $response;
    }

    public function uploadChunk(
        ApiClient $apiClient,
        AssetUrlInterface $assetUrl,
        UploadedFile $file,
        string $assetId,
        int $expectedStatusCode,
        ?array $body = null,
    ): Response {
        $response = $apiClient->postChunkFile(
            $assetUrl->getCreateChunkPath($assetId),
            $file,
            $body ?: [
                'offset' => 0,
                'size' => (int) $file->getSize(),
            ]
        );

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            sprintf(
                'Create chunk failed. Api request status code (%s) and content (%s)',
                $response->getStatusCode(),
                $response->getContent()
            )
        );

        return $response;
    }

    public function finishUpload(
        ApiClient $apiClient,
        AssetUrlInterface $assetUrl,
        UploadedFile $file,
        string $assetId,
        int $expectedStatusCode,
    ): Response {
        $checksum = FileHelper::checksumFromPath((string)$file->getRealPath());
        $response = $apiClient->patch(
            $assetUrl->getFinishUploadPath($assetId),
            [
                'checksum' => $checksum
            ]
        );

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            sprintf(
                'Asset postprocess failed. Api request status code (%s) and content (%s)',
                $response->getStatusCode(),
                $response->getContent()
            )
        );

        return $response;
    }

    public function delete(
        ApiClient $apiClient,
        AssetUrlInterface $assetUrl,
        string $assetId,
        int $expectedStatusCode,
    ): Response {
        $response = $apiClient->delete($assetUrl->getSingleAssetPath($assetId));

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            sprintf(
                'Asset delete failed. Api request status code (%s) and content (%s)',
                $response->getStatusCode(),
                $response->getContent()
            )
        );

        return $response;
    }

    /**
     * @throws SerializerException
     */
    public function uploadAsset(ApiClient $apiClient, AssetUrlInterface $assetUrl, string $filePath): AbstractEntityDto
    {
        $file = $this->getFile($filePath);

        $response = $this->createAsset($apiClient, $assetUrl, $file, Response::HTTP_CREATED);
        $id = json_decode($response->getContent(), true)['id'];
        $this->uploadChunk($apiClient, $assetUrl, $file, $id,Response::HTTP_CREATED);

        return $this->deserializeAsset(
            $this->finishUpload($apiClient, $assetUrl, $file, $id,Response::HTTP_OK),
            $assetUrl
        );
    }

    /**
     * @throws SerializerException
     */
    public function getSingleAsset(
        ApiClient $apiClient,
        AssetUrlInterface $imageUrl,
        string $assetId
    ): AbstractEntityDto
    {
        return $this->deserializeAsset(
            $apiClient->get($imageUrl->getSingleAssetPath($assetId)),
            $imageUrl
        );
    }

    /**
     * @throws FilesystemException
     */
    protected function assertFileInFilesystemExists(Filesystem $filesystem, string $filePath): void
    {
        $this->assertTrue(
            $filesystem->fileExists($filePath),
            sprintf(
                'File (%s) not exists',
                $filePath
            )
        );
    }

    /**
     * @throws SerializerException
     */
    private function deserializeAsset(Response $response, AssetUrlInterface $imageUrl): AbstractEntityDto
    {
        return $this->serializer->deserialize($response->getContent(), $imageUrl->getSerializeClassString());
    }
}
