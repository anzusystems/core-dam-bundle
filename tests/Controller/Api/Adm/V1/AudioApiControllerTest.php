<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AudioUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
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
