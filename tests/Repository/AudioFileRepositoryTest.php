<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Repository;

use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;

final class AudioFileRepositoryTest extends CoreDamKernelTestCase
{
    private AudioFileRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getService(AudioFileRepository::class);
    }

    public function testFindDetachedByAssetSkipsAudioHeldByASlot(): void
    {
        $audio = $this->repository->find(AudioFixtures::AUDIO_ID_1);
        self::assertInstanceOf(AudioFile::class, $audio);

        self::assertCount(0, $this->repository->findDetachedByAsset($audio->getAsset()));
    }

    public function testFindDetachedByAssetReturnsSupersededAudio(): void
    {
        $audio = $this->repository->find(AudioFixtures::AUDIO_ID_1);
        self::assertInstanceOf(AudioFile::class, $audio);
        $asset = $audio->getAsset();

        $this->detachAudioFromSlots($audio);

        $detached = $this->repository->findDetachedByAsset($asset);
        self::assertCount(1, $detached);

        $found = $detached->first();
        self::assertInstanceOf(AudioFile::class, $found);
        self::assertSame((string) $audio->getId(), (string) $found->getId());
    }

    /**
     * AUDIO_ID_2 is the only fixture audio never stored, so deleting it touches no storage and the
     * rollback leaves the fixture set intact.
     */
    public function testDeletingAssetAlsoRemovesSupersededAudio(): void
    {
        $audio = $this->repository->find(AudioFixtures::AUDIO_ID_2);
        self::assertInstanceOf(AudioFile::class, $audio);
        self::assertSame('', $audio->getAssetAttributes()->getFilePath());

        $asset = $audio->getAsset();
        $this->stripSlotsAndMainFile($audio);

        $this->getService(AssetFacade::class)->delete($asset);
        $this->entityManager->clear();

        self::assertNull($this->repository->find(AudioFixtures::AUDIO_ID_2));
    }

    private function detachAudioFromSlots(AudioFile $audio): void
    {
        foreach ($audio->getSlots() as $slot) {
            $slot->setAudio(null);
            $audio->getSlots()->removeElement($slot);
        }
        $this->entityManager->flush();
    }

    private function stripSlotsAndMainFile(AudioFile $audio): void
    {
        $asset = $audio->getAsset();
        foreach ($audio->getSlots() as $slot) {
            $asset->getSlots()->removeElement($slot);
            $audio->getSlots()->removeElement($slot);
            $this->entityManager->remove($slot);
        }
        $asset->setMainFile(null);
        $this->entityManager->flush();
    }
}
