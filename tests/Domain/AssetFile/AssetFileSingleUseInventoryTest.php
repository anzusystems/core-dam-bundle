<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Command\AssetFileSingleUseInventoryCommand;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseInventoryFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\ImageFileUsage;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AssetFileSingleUseInventoryTest extends CoreDamKernelTestCase
{
    use AssetFileGroupUsageReaderAnsweringTrait;

    private const string CMS_SLUG = 'cms';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '01994000-0000-7000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '01994000-0000-7000-8000-000000000002';
    private const string MISSING_ROOT_ID = '01994000-0000-7000-8000-0000000000ff';

    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetFileManager $assetFileManager;
    private AssetFileRepository $assetFileRepository;
    private int $extIdSequence = App::ZERO;
    private string $summary = App::EMPTY_STRING;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->assetFileManager = $this->getService(AssetFileManager::class);
        $this->assetFileRepository = $this->getService(AssetFileRepository::class);
    }

    public function testInventoryListsOnlyGroupsWithTwoOrMoreHolders(): void
    {
        $none = $this->createSingleUseImage();
        $one = $this->createSingleUseImage();
        $unanswered = $this->createSingleUseImage();
        $two = $this->createSingleUseImage();

        $lines = $this->runInventory([
            (string) $none->getId() => new ImageFileUsage(true, []),
            (string) $one->getId() => new ImageFileUsage(true, [$this->holder()]),
            (string) $two->getId() => new ImageFileUsage(true, [$this->holder(), $this->holder(self::OTHER_HOLDER_ID)]),
        ]);

        $rows = $this->rowsMentioning($lines, [$none, $one, $unanswered, $two]);
        self::assertCount(1, $rows);
        $columns = explode(',', $rows[App::ZERO]);
        self::assertSame((string) $two->getId(), $columns[App::ZERO]);
        self::assertSame((string) $two->getAsset()->getId(), $columns[1]);
        self::assertSame((string) $two->getLicence()->getId(), $columns[2]);
        self::assertSame(
            self::HOLDER_NAME . '/' . self::HOLDER_ID . ';' . self::HOLDER_NAME . '/' . self::OTHER_HOLDER_ID,
            $columns[4],
        );
    }

    public function testSummaryGoesToStderrAndKeepsStdoutToCsv(): void
    {
        $this->createSingleUseImage();

        $lines = $this->runInventory([]);

        self::assertStringStartsWith('Checked ', $this->summary);
        self::assertSame('rootId,assetId,licenceId,recordedHolder,holders,firstUsedAt', $lines[App::ZERO]);
        foreach ($lines as $line) {
            self::assertStringNotContainsString('Checked ', $line);
        }
    }

    public function testGroupAnsweredWithNoHoldersIsNotCountedAsUnanswered(): void
    {
        $listsNone = $this->createSingleUseImage();
        $this->createSingleUseImage();

        $this->runInventory([
            ImageFixtures::IMAGE_ID_1_1 => new ImageFileUsage(true, []),
            (string) $listsNone->getId() => new ImageFileUsage(true, []),
        ]);

        self::assertStringEndsWith('0 with 2+ holders, 1 unanswered', $this->summary);
    }

    public function testTakenOverGroupIsListedOnceWithTheColumnsOfItsRoot(): void
    {
        $root = $this->createClaimedSingleUseImage();
        $copy = $this->createSingleUseImage((string) $root->getId());

        $lines = $this->runInventory([
            (string) $root->getId() => new ImageFileUsage(true, [$this->holder()]),
            (string) $copy->getId() => new ImageFileUsage(true, [$this->holder(self::OTHER_HOLDER_ID)]),
        ]);

        $rows = $this->rowsMentioning($lines, [$root, $copy]);
        self::assertCount(1, $rows);
        $columns = explode(',', $rows[App::ZERO]);
        self::assertSame((string) $root->getId(), $columns[App::ZERO]);
        self::assertSame((string) $root->getAsset()->getId(), $columns[1]);
        self::assertSame((string) $root->getLicence()->getId(), $columns[2]);
        self::assertSame(self::HOLDER_NAME . '/' . self::HOLDER_ID, $columns[3]);
        self::assertEqualsCanonicalizing(
            [self::HOLDER_NAME . '/' . self::HOLDER_ID, self::HOLDER_NAME . '/' . self::OTHER_HOLDER_ID],
            explode(';', $columns[4]),
        );
    }

    public function testGroupWithMissingRootIsStillListedAndCounted(): void
    {
        $copy = $this->createSingleUseImage(self::MISSING_ROOT_ID);

        $lines = $this->runInventory([
            (string) $copy->getId() => new ImageFileUsage(true, [$this->holder(), $this->holder(self::OTHER_HOLDER_ID)]),
        ]);

        $rows = array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, self::MISSING_ROOT_ID . ','),
        ));
        self::assertCount(1, $rows);
        $columns = explode(',', $rows[App::ZERO]);
        self::assertSame(self::MISSING_ROOT_ID, $columns[App::ZERO]);
        self::assertSame([App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING], array_slice($columns, 1, 3));
        self::assertSame(App::EMPTY_STRING, $columns[5]);
        self::assertEqualsCanonicalizing(
            [self::HOLDER_NAME . '/' . self::HOLDER_ID, self::HOLDER_NAME . '/' . self::OTHER_HOLDER_ID],
            explode(';', $columns[4]),
        );
        self::assertStringContainsString('1 with 2+ holders', $this->summary);
    }

    public function testInventoryWritesNothing(): void
    {
        $image = $this->createClaimedSingleUseImage();
        $imageId = (string) $image->getId();
        $before = $this->usageState($image);

        $this->runInventory([
            $imageId => new ImageFileUsage(true, [$this->holder(), $this->holder(self::OTHER_HOLDER_ID)]),
        ]);
        $this->entityManager->clear();

        $reloaded = $this->assetFileRepository->find($imageId);
        self::assertInstanceOf(ImageFile::class, $reloaded);
        self::assertSame($before, $this->usageState($reloaded));
    }

    /**
     * @param array<string, ImageFileUsage> $usage
     *
     * @return list<string>
     */
    private function runInventory(array $usage): array
    {
        $command = new AssetFileSingleUseInventoryCommand(
            new AssetFileSingleUseInventoryFacade(
                $this->getService(ImageFileRepository::class),
                $this->assetFileRepository,
                $this->groupUsageReaderAnswering($usage),
                $this->assetFileManager,
            ),
        );
        $tester = new CommandTester(new Command(code: $command));
        $tester->execute([], ['capture_stderr_separately' => true]);
        $this->summary = trim($tester->getErrorOutput());

        return explode(PHP_EOL, trim($tester->getDisplay()));
    }

    /**
     * @param list<string> $lines
     * @param list<ImageFile> $images
     *
     * @return list<string>
     */
    private function rowsMentioning(array $lines, array $images): array
    {
        return array_values(array_filter(
            $lines,
            static function (string $line) use ($images): bool {
                foreach ($images as $image) {
                    if (str_contains($line, (string) $image->getId())) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /**
     * @return list<string>
     */
    private function usageState(ImageFile $image): array
    {
        $attributes = $image->getAssetAttributes();

        return [
            $attributes->getUsedByHolderName(),
            $attributes->getUsedByHolderId(),
            (string) $attributes->getUsedByCheckAfter()?->getTimestamp(),
        ];
    }

    private function createSingleUseImage(?string $takenOverFromId = null): ImageFile
    {
        $licence = $this->createLicence();
        $image = $this->imageFactory->createFromUrl($licence, 'https://example.test/inventory-' . $this->extIdSequence . '.jpg');
        $this->assetFactory->createForAssetFile($image, $licence);
        $this->imageManager->create($image);
        $image->getFlags()->setSingleUse(true);
        if (null !== $takenOverFromId) {
            $image->getAssetAttributes()->setTakenOverFromId($takenOverFromId);
        }
        $this->assetFileManager->flush();

        return $image;
    }

    private function createClaimedSingleUseImage(): ImageFile
    {
        $image = $this->createSingleUseImage();
        $this->assetFileManager->updateUsage($image, UsageClaim::fromHolder($this->holder()), usedByCheckAfter: AssetFileManager::nextUsageCheck());

        return $image;
    }

    private function holder(string $id = self::HOLDER_ID): ImageHolderDto
    {
        return new ImageHolderDto()
            ->setName(self::HOLDER_NAME)
            ->setId($id)
        ;
    }

    private function createLicence(): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);

        return $this->assetLicenceManager->create(
            new AssetLicence()
                ->setExtId('inventory-' . ++$this->extIdSequence)
                ->setExtSystem($extSystem)
        );
    }
}
