<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AudioUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use FFMpeg\FFProbe;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

final class AudioApiControllerTest extends AbstractAssetFileApiController
{
    private const TEST_DATA_FILENAME = 'audio_example.mp3';

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testUpload(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $audioUrl = new AudioUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $audio = $this->uploadAsset(
            $client,
            $audioUrl,
            self::TEST_DATA_FILENAME,
        );

        $imageEntity = $this->entityManager->find(AudioFile::class, $audio->getId());
        $filesystem = $this->filesystemProvider->getFilesystemByStorable($imageEntity);
        $originImagePath = $this->nameGenerator->getPath($imageEntity->getAssetAttributes()->getFilePath());
        $this->assertFileInFilesystemExists($filesystem, $originImagePath->getFullPath());

        $this->delete(
            $client,
            $audioUrl,
            $audio->getId(),
            Response::HTTP_NO_CONTENT
        );
        $this->assertEquals(0, count($filesystem->listContents($originImagePath->getDir())->toArray()));
    }

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testMakePublicTranscodesWavToMp3(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $audioUrl = new AudioUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $audio = $this->uploadAsset($client, $audioUrl, 'audio_example.wav');
        $audioEntity = $this->entityManager->find(AudioFile::class, $audio->getId());
        $sourceFilesystem = $this->filesystemProvider->getFilesystemByStorable($audioEntity);
        $sourcePath = $audioEntity->getAssetAttributes()->getFilePath();
        $sourceChecksum = sha1($sourceFilesystem->read($sourcePath));

        $response = $client->patch($audioUrl->getMakePublicPath($audio->getId()), ['slug' => 'wav-episode']);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $mainRoute = static::getContainer()->get(AssetFileRouteRepository::class)->findMainByAssetFile($audio->getId());
        $this->assertNotNull($mainRoute);
        $routePath = $mainRoute->getUri()->getPath();
        $this->assertStringEndsWith('.mp3', $routePath);

        $publicFilesystem = $this->filesystemProvider->getPublicFilesystem($audioEntity);
        $this->assertFileInFilesystemExists($publicFilesystem, $routePath);

        $publicFile = $this->filesystemProvider->getTmpFileSystem()->writeTmpFileFromFilesystem($publicFilesystem, $routePath);
        $publicStream = FFProbe::create()->streams((string) $publicFile->getRealPath())->audios()->first();
        $this->assertNotNull($publicStream);
        $this->assertSame('mp3', $publicStream->get('codec_name'));
        // Stream bitrate is the nominal CBR value; format bit_rate is skewed by the ID3 header on short files.
        $this->assertSame(
            AudioMimeTypes::PUBLIC_CONVERSION_BITRATE_KBPS * 1_000,
            (int) $publicStream->get('bit_rate')
        );

        $this->assertSame(
            $sourceChecksum,
            sha1($sourceFilesystem->read($sourcePath)),
            'Conversion must not touch the stored original.'
        );

        $response = $client->patch($audioUrl->getMakePrivatePath($audio->getId()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($publicFilesystem->fileExists($routePath));

        $this->delete($client, $audioUrl, $audio->getId(), Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testMakePublicKeepsBrowserReadyAudioBytes(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $audioUrl = new AudioUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $audio = $this->uploadAsset($client, $audioUrl, self::TEST_DATA_FILENAME);
        $audioEntity = $this->entityManager->find(AudioFile::class, $audio->getId());
        $sourceFilesystem = $this->filesystemProvider->getFilesystemByStorable($audioEntity);
        $sourceChecksum = sha1($sourceFilesystem->read($audioEntity->getAssetAttributes()->getFilePath()));

        $response = $client->patch($audioUrl->getMakePublicPath($audio->getId()), ['slug' => 'mp3-episode']);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $mainRoute = static::getContainer()->get(AssetFileRouteRepository::class)->findMainByAssetFile($audio->getId());
        $this->assertNotNull($mainRoute);
        $publicFilesystem = $this->filesystemProvider->getPublicFilesystem($audioEntity);
        $this->assertSame(
            $sourceChecksum,
            sha1($publicFilesystem->read($mainRoute->getUri()->getPath())),
            'Browser ready audio must be published as a raw byte copy.'
        );

        $this->delete($client, $audioUrl, $audio->getId(), Response::HTTP_NO_CONTENT);
    }

    public function testSetSlotSuccess(): void
    {
        $this->testSlotsSuccess(
            $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_1),
            $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_2),
            'bonus',
            new AudioUrl(1)
        );
    }
}
