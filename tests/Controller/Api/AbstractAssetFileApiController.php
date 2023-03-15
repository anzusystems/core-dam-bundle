<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api;

use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AssetUrlInterface;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAssetFileApiController extends AbstractApiController
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
        $file = new File(AbstractAssetFileFixtures::DATA_PATH . $filePath);

        return new UploadedFile($file->getRealPath(), $file->getFilename());
    }

    public function addToSlot(
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
            $assetUrl->getAddToSlotPath($assetId, $position),
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

    protected function testSlotsSuccess(
        AssetFile $firstAssetFile,
        AssetFile $secondAssetFile,
        string $newSlot,
        AssetUrlInterface $url,
    ): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $primarySlotName = $firstAssetFile->getSlots()->first()->getName();
        $asset = $firstAssetFile->getAsset();
        $assetId = (string) $firstAssetFile->getAsset()->getId();
        $oldAsset = $secondAssetFile->getAsset();
        $oldAssetId = $secondAssetFile->getAsset()->getId();

        // Set assetFile to asset slot (originAsset of asset file should be in draft status)
        $response = $client->patch($url->setToSlot($assetId, $secondAssetFile->getId(), $newSlot));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->entityManager->refresh($asset);

        /** @var AssetSlot $assignedToNewSlot */
        $assignedToNewSlot = $asset->getSlots()->filter(fn (AssetSlot $slot): bool => $slot->getName() === $newSlot)->first();
        $this->assertInstanceOf(AssetSlot::class, $assignedToNewSlot);
        $this->assertSame($assignedToNewSlot->getAssetFile()->getId(), $secondAssetFile->getId());
        $this->assertSame(AssetStatus::Default, $oldAsset->getAttributes()->getStatus());
        $this->assertNull($oldAsset->getMainFile());
        $this->assertCount(2, $asset->getSlots());

        $response = $client->patch($url->setMainFilePath($assetId, $secondAssetFile->getId()));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $asset = $this->entityManager->find(Asset::class, $assetId);
        $this->assertSame($secondAssetFile->getId(), $asset->getMainFile()->getId());

        // Set assetFile to origin asset
        $response = $client->patch($url->setToSlot($oldAssetId, $secondAssetFile->getId(), $newSlot));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $oldAsset = $this->entityManager->find(Asset::class, $oldAssetId);
        $this->assertSame($secondAssetFile->getId(), $oldAsset->getMainFile()->getId());
        $this->assertCount(1, $oldAsset->getSlots());

        $asset = $this->entityManager->find(Asset::class, $assetId);
        $this->assertSame($firstAssetFile->getId(), $asset->getMainFile()->getId());
        $this->assertCount(1, $asset->getSlots());

        // Duplicate Asset file to second slot
        $response = $client->patch($url->setToSlot($assetId, $firstAssetFile->getId(), $newSlot));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $asset = $this->entityManager->find(Asset::class, $assetId);
        $this->assertCount(2, $asset->getSlots());

        // Delete primary slot
        $response = $client->delete($url->setToSlot($assetId, $firstAssetFile->getId(), $primarySlotName));
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Delete secondary slot
        $response = $client->delete($url->setToSlot($assetId, $firstAssetFile->getId(), $newSlot));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @throws SerializerException
     */
    private function deserializeAsset(Response $response, AssetUrlInterface $imageUrl): AbstractEntityDto
    {
        return $this->serializer->deserialize($response->getContent(), $imageUrl->getSerializeClassString());
    }
}
