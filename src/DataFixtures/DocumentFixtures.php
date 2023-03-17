<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentFactory;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<DocumentFile>
 */
final class DocumentFixtures extends AbstractAssetFileFixtures
{
    public const DOC_ID_1 = 'ac967cf4-0ea9-499e-be2a-13bf0b63eabe';
    public const DOC_ID_2 = 'ad967cf4-0ea9-499e-be2a-13bf0b63eabc';

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly DocumentFactory $documentFactory,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            AssetLicenceFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return DocumentFile::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var DocumentFile $video */
        foreach ($progressBar->iterate($this->getData()) as $video) {
            $video = $this->documentManager->create($video);

            $this->addToRegistry($video, (int) $video->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        /** @var AssetLicence $licence */
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $file = $this->getFile($fileSystem, 'doc2.csv');
        $document = $this->documentFactory->createFromFile(
            $file,
            $licence,
            self::DOC_ID_1
        );
        $asset = $document->getAsset();
        $asset->getAssetFlags()->setDescribed(true);
        $asset->getMetadata()->setCustomData([
            'title' => 'CSV',
        ]);
        $document->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($document)->storeAndProcess($document, $file);

        yield $document;

        $file = $this->getFile($fileSystem, 'doc3.html');
        $document = $this->documentFactory->createFromFile(
            $file,
            $licence,
            self::DOC_ID_2
        );
        $asset = $document->getAsset();
        $asset->getAssetFlags()->setDescribed(true);
        $asset->getMetadata()->setCustomData([
            'title' => 'HTML',
        ]);
        $document->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($document)->storeAndProcess($document, $file);

        yield $document;
    }
}
