<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\VideoUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

final class VideoApiControllerTest extends AbstractAssetFileApiControllerTest
{
    private const TEST_DATA_FILENAME = 'video_example.mp4';

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testUpload(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $videoUrl = new VideoUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $video = $this->uploadAsset(
            $client,
            $videoUrl,
            self::TEST_DATA_FILENAME,
        );

        $imageEntity = $this->entityManager->find(VideoFile::class, $video->getId());
        $filesystem = $this->filesystemProvider->getFilesystemByStorable($imageEntity);
        $originImagePath = $this->nameGenerator->getPath($imageEntity->getAssetAttributes()->getFilePath());
        $this->assertFileInFilesystemExists($filesystem, $originImagePath->getFullPath());

        $this->delete(
            $client,
            $videoUrl,
            $video->getId(),
            Response::HTTP_NO_CONTENT
        );
        $this->assertEquals(0, count($filesystem->listContents($originImagePath->getDir())->toArray()));
    }
}
