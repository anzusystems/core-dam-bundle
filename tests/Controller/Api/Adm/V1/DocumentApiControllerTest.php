<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\DocumentUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

final class DocumentApiControllerTest extends AbstractAssetFileApiControllerTest
{
    private const TEST_DATA_FILENAME = 'doc.txt';

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testUpload(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $documentUrl = new DocumentUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $document = $this->uploadAsset(
            $client,
            $documentUrl,
            self::TEST_DATA_FILENAME,
        );

        $documentEntity = $this->entityManager->find(DocumentFile::class, $document->getId());
        $filesystem = $this->filesystemProvider->getFilesystemByStorable($documentEntity);
        $originImagePath = $this->nameGenerator->getPath($documentEntity->getAssetAttributes()->getFilePath());
        $this->assertFileInFilesystemExists($filesystem, $originImagePath->getFullPath());

        $this->delete(
            $client,
            $documentUrl,
            $document->getId(),
            Response::HTTP_NO_CONTENT
        );
        $this->assertEquals(0, count($filesystem->listContents($originImagePath->getDir())->toArray()));
    }
}
