<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Command\TtsClearExpiredAudioCommand;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsExpiredAudioFixtures;
use Symfony\Component\Console\Tester\CommandTester;

/** Reaps files seeded by {@see TtsExpiredAudioFixtures}: an orphan (asset row gone) must not block the rest of the batch. */
final class TtsClearExpiredAudioCommandTest extends CoreDamKernelTestCase
{
    public function testOrphanedAudioDoesNotBlockRemovalOfTheRestOfTheBatch(): void
    {
        self::assertInstanceOf(AudioFile::class, $this->entityManager->find(AudioFile::class, TtsExpiredAudioFixtures::ORPHAN_AUDIO_ID));
        self::assertInstanceOf(AudioFile::class, $this->entityManager->find(AudioFile::class, TtsExpiredAudioFixtures::SUPERSEDED_AUDIO_ID));
        $this->entityManager->clear();

        (new CommandTester($this->getService(TtsClearExpiredAudioCommand::class)))->execute([]);

        $this->entityManager->clear();
        self::assertNull(
            $this->entityManager->find(AudioFile::class, TtsExpiredAudioFixtures::ORPHAN_AUDIO_ID),
            'An orphaned expired audio file must be removable.'
        );
        self::assertNull(
            $this->entityManager->find(AudioFile::class, TtsExpiredAudioFixtures::SUPERSEDED_AUDIO_ID),
            'An expired audio file must be removed even when another file in the batch fails.'
        );
    }
}
