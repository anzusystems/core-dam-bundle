<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsAudioFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsVoiceFixtures;
use Symfony\Component\HttpFoundation\File\File;

// Crash-recovery invariant: a re-run finalize must replace the stale master, not collide with slot/TtsAsset PK.
final class TtsAudioFactoryTest extends CoreDamKernelTestCase
{
    public function testRerunReplacesStaleMasterInsteadOfColliding(): void
    {
        $asset = $this->audioAsset();
        $masterSlotName = $this->getService(Config::class)->getMasterSlotName();

        $first = $this->entityManager->wrapInTransaction(
            fn () => $this->factory()->create($this->input(), $asset)
        );
        $second = $this->entityManager->wrapInTransaction(
            fn () => $this->factory()->create($this->input(), $asset)
        );

        $masterSlots = $asset->getSlots()->filter(
            static fn ($slot): bool => $slot->getName() === $masterSlotName
        );
        self::assertCount(1, $masterSlots, 'Re-run must not create a second master slot.');
        self::assertSame($second->masterAudio, $masterSlots->first()->getAudio());

        self::assertNotNull($first->masterAudio->getExpireAt(), 'Displaced stale master must be reapable.');
        self::assertCount(0, $first->masterAudio->getSlots());

        $ttsAssets = $this->entityManager->getRepository(TtsAsset::class)->findBy(['asset' => $asset]);
        self::assertCount(1, $ttsAssets, 'Re-run must reuse the TtsAsset row (PK is the asset).');
        self::assertSame($second->ttsAsset, $ttsAssets[0]);
    }

    private function factory(): TtsAudioFactory
    {
        return $this->getService(TtsAudioFactory::class);
    }

    private function input(): TtsAudioCreationInput
    {
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $voice = $this->getService(VoiceResolver::class)->resolve(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG, $extSystem);

        $tmpFs = $this->getService(FileSystemProvider::class)->getTmpFileSystem();
        $rel = $tmpFs->writeTmpFileFromBytes('mp3-bytes', 'mp3');

        return new TtsAudioCreationInput(
            audioFile: AdapterFile::createFromBaseFile(new File($tmpFs->extendPath($rel)), $tmpFs),
            family: $voice->getVoiceFamily(),
            voice: $voice,
            licence: $this->audioAsset()->getLicence(),
            sourceTextHash: hash('sha256', 'text'),
            sourceTextSnapshot: 'text',
        );
    }

    private function audioAsset(): Asset
    {
        $audio = $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_1);
        self::assertInstanceOf(AudioFile::class, $audio);

        return $audio->getAsset();
    }
}
