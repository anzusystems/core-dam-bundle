<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\MetadataProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

// The user save and the worker run in different processes; the user save is simulated by a bulk DQL update that skips the identity map.
final class MetadataProcessorTest extends CoreDamKernelTestCase
{
    private const string DATA_PATH = __DIR__ . '/../../data/Files/';
    private const string FILE_NAME = 'exifTags.jpg';

    public function testWorkerKeepsMetadataSavedByUserAfterItLoadedTheAsset(): void
    {
        $imageFile = $this->createImage();
        $asset = $imageFile->getAsset();
        $userCustomData = ['title' => 'saved by the user'];
        $this->entityManager->createQuery(sprintf('UPDATE %s m SET m.customData = :customData WHERE m.id = :id', AssetMetadata::class))
            ->setParameter('customData', $userCustomData, Types::JSON)
            ->setParameter('id', $asset->getMetadata()->getId())
            ->execute();
        $this->entityManager->createQuery(sprintf('UPDATE %s a SET a.assetFlags.described = true WHERE a.id = :id', Asset::class))
            ->setParameter('id', $asset->getId())
            ->execute();
        self::assertTrue($asset->getAssetFlags()->isNotDescribed(), 'The worker must start with the stale snapshot.');

        $this->getService(MetadataProcessor::class)->process($imageFile, $this->getFile());

        self::assertSame($userCustomData, $asset->getMetadata()->getCustomData());
        self::assertTrue($asset->getAssetFlags()->isDescribed());
        self::assertTrue($asset->getAssetFlags()->isNotAutocompletedMetadata());
    }

    public function testProcessedMetadataFlagIsSetOnlyAfterSuggestersFinished(): void
    {
        $imageFile = $this->createImage();

        $this->getService(AssetMetadataProcessor::class)->provideMetaData($imageFile, $this->getFile());

        self::assertFalse($imageFile->getFlags()->isProcessedMetadata(), 'A flush inside the suggesters must not commit the flag.');

        $this->getService(MetadataProcessor::class)->process($imageFile, $this->getFile());

        self::assertTrue($imageFile->getFlags()->isProcessedMetadata());
    }

    private function getFile(): AdapterFile
    {
        return new AdapterFile(
            path: self::DATA_PATH . self::FILE_NAME,
            adapterPath: self::FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(self::DATA_PATH),
        );
    }

    private function createImage(): ImageFile
    {
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::LICENCE_ID);
        self::assertNotNull($licence);
        $imageFile = $this->getService(ImageFactory::class)->createFromFile(
            $this->getFile(),
            $licence,
            Uuid::v4()->toRfc4122(),
        );
        self::assertInstanceOf(ImageFile::class, $imageFile);
        $imageFile->getFlags()->setProcessedMetadata(false);

        return $this->getService(ImageManager::class)->create($imageFile);
    }
}
