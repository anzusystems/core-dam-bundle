<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures\Provider;

use AnzuSystems\CommonBundle\Helper\UuidHelper;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Faker\Factory;
use Faker\Generator;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;

final class FixtureImageProvider
{
    use SerializerAwareTrait;
    use OutputUtilTrait;
    private const ASSET_TITLE_SENTENCE_LENGTH = 5;
    private const ASSET_DESCRIPTION_TEXT_LENGTH = 256;

    private readonly Generator $fakerGenerator;

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
        private readonly ImageFactory $imageFactory,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->fakerGenerator = Factory::create();
    }

    /**
     * @throws FilesystemException
     */
    public function loadFixtures(AssetLicence $assetLicence): void
    {
        $directoryListing = $this->fileSystemProvider->getFixturesFileSystem()->listContents('');
        $fixturesFileSystem = $this->fileSystemProvider->getFixturesFileSystem();

        $this->setAssignedGenerator(ImageFile::class);
        $progress = $this->outputUtil->createProgressBar();

        $i = 0;
        /** @var FileAttributes $item */
        foreach ($directoryListing as $item) {
            if (StorageAttributes::TYPE_FILE === $item->type()) {
                $file = new AdapterFile(
                    path: $fixturesFileSystem->extendPath($item->path()),
                    adapterPath: $item->path(),
                    filesystem: $fixturesFileSystem
                );

                $imageFileId = UuidHelper::getAnzuId(
                    resourceName: 'image',
                    system: $assetLicence->getExtSystem()->getSlug(),
                    id: ++$i,
                    groupId: (int) $assetLicence->getExtId()
                );

                try {
                    $assetFile = $this->imageFactory->createFromFile($file, $assetLicence, $imageFileId);
                    $assetFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
                } catch (DomainException) {
                    continue;
                }

                $assetFile->getAsset()
                    ->setLicence($assetLicence)
                    ->setExtSystem($assetLicence->getExtSystem())
                ;
                $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile, $file);

                $progress->advance();
            }
        }

        $progress->finish();
        $this->outputUtil->writeln('');
    }

    private function setAssignedGenerator(string $className): void
    {
        $meta = $this->entityManager->getClassMetadata($className);
        $meta->setIdGenerator(new AssignedGenerator());
        $meta->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
    }
}
