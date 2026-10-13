<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Repository;

use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class VideoFileRepositoryTest extends CoreDamKernelTestCase
{
    private const string URL = 'http://core-dam.sme.localhost/download-file?file=video_example.mp4&case=repository';
    private const string OTHER_URL = 'http://core-dam.sme.localhost/download-file?file=video_example.mp4&case=other';

    private VideoFileRepository $repository;
    private VideoFile $older;
    private VideoFile $newer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(VideoFileRepository::class);
        $older = $this->repository->find(VideoFixtures::VIDEO_ID_2);
        $newer = $this->repository->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $older);
        self::assertInstanceOf(VideoFile::class, $newer);
        $this->older = $older;
        $this->newer = $newer;

        $this->newer->setLicence($this->older->getLicence());
        $this->older->setCreatedAt(new DateTimeImmutable('-2 days'));
        $this->newer->setCreatedAt(new DateTimeImmutable('-1 day'));
        foreach ([$this->older, $this->newer] as $videoFile) {
            $videoFile->getAssetAttributes()->setOriginUrl(self::URL);
        }
        $this->entityManager->flush();
    }

    #[DataProvider('statusDataProvider')]
    public function testFindOneInProgressOrProcessedByUrlAndLicenceByStatuses(
        AssetFileProcessStatus $olderStatus,
        AssetFileProcessStatus $newerStatus,
        ?string $expected,
    ): void {
        $this->prepareStatuses($olderStatus, $newerStatus);

        $found = $this->repository->findOneInProgressOrProcessedByUrlAndLicence(self::URL, $this->older->getLicence());

        self::assertSame(match ($expected) {
            'older' => $this->older,
            'newer' => $this->newer,
            default => null,
        }, $found);
    }

    public static function statusDataProvider(): array
    {
        return [
            'uploaded older, failed newer' => [
                'olderStatus' => AssetFileProcessStatus::Uploaded,
                'newerStatus' => AssetFileProcessStatus::Failed,
                'expected' => 'older',
            ],
            'stored older, failed newer' => [
                'olderStatus' => AssetFileProcessStatus::Stored,
                'newerStatus' => AssetFileProcessStatus::Failed,
                'expected' => 'older',
            ],
            'processed older, failed newer' => [
                'olderStatus' => AssetFileProcessStatus::Processed,
                'newerStatus' => AssetFileProcessStatus::Failed,
                'expected' => 'older',
            ],
            'processed older, duplicate newer' => [
                'olderStatus' => AssetFileProcessStatus::Processed,
                'newerStatus' => AssetFileProcessStatus::Duplicate,
                'expected' => 'older',
            ],
            'failed older, duplicate newer' => [
                'olderStatus' => AssetFileProcessStatus::Failed,
                'newerStatus' => AssetFileProcessStatus::Duplicate,
                'expected' => null,
            ],
            'processed older, uploaded newer returns newest' => [
                'olderStatus' => AssetFileProcessStatus::Processed,
                'newerStatus' => AssetFileProcessStatus::Uploaded,
                'expected' => 'newer',
            ],
        ];
    }

    #[DataProvider('mismatchDataProvider')]
    public function testFindOneInProgressOrProcessedByUrlAndLicenceIgnoresFileWithOtherLicenceOrUrl(
        bool $otherLicence,
        bool $otherUrl,
    ): void {
        $this->prepareStatuses(AssetFileProcessStatus::Failed, AssetFileProcessStatus::Processed);
        $this->prepareNewerMismatch($otherLicence, $otherUrl);

        self::assertNull($this->repository->findOneInProgressOrProcessedByUrlAndLicence(self::URL, $this->older->getLicence()));
    }

    public static function mismatchDataProvider(): array
    {
        return [
            'other licence' => ['otherLicence' => true, 'otherUrl' => false],
            'other url' => ['otherLicence' => false, 'otherUrl' => true],
        ];
    }

    private function prepareStatuses(AssetFileProcessStatus $olderStatus, AssetFileProcessStatus $newerStatus): void
    {
        $this->older->getAssetAttributes()->setStatus($olderStatus);
        $this->newer->getAssetAttributes()->setStatus($newerStatus);
        $this->entityManager->flush();
    }

    private function prepareNewerMismatch(bool $otherLicence, bool $otherUrl): void
    {
        if ($otherLicence) {
            $this->newer->setLicence($this->findOtherLicence());
        }
        if ($otherUrl) {
            $this->newer->getAssetAttributes()->setOriginUrl(self::OTHER_URL);
        }
        $this->entityManager->flush();
    }

    private function findOtherLicence(): AssetLicence
    {
        foreach ($this->getService(AssetLicenceRepository::class)->findAll() as $licence) {
            if ($licence->isNot($this->older->getLicence())) {
                return $licence;
            }
        }

        self::fail('No other licence in fixtures.');
    }
}
