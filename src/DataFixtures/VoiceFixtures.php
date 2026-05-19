<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceManager;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Voice>
 */
final class VoiceFixtures extends AbstractFixtures
{
    public const string VOICE_SK_DEFAULT_GOOGLE = 'b1f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_SK_DEFAULT_ELEVENLABS = 'b2f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_SK_SECONDARY_ELEVENLABS = 'b3f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_EN_DEFAULT_ELEVENLABS = 'b4f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_EN_DEFAULT_GOOGLE = 'b5f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';

    public function __construct(
        private readonly VoiceManager $voiceManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['dev', 'test'];
    }

    public static function getDependencies(): array
    {
        return [
            VoiceFamilyFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return Voice::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Voice $voice */
        foreach ($progressBar->iterate($this->getData()) as $voice) {
            $voice = $this->voiceManager->create($voice);
            $this->addToRegistry($voice, (string) $voice->getId());
        }
    }

    private function getData(): Generator
    {
        $families = $this->entityManager->getRepository(VoiceFamily::class)->findBy(['id' => [
            VoiceFamilyFixtures::VOICE_FAMILY_SK_DEFAULT,
            VoiceFamilyFixtures::VOICE_FAMILY_SK_SECONDARY,
            VoiceFamilyFixtures::VOICE_FAMILY_EN_DEFAULT,
        ]]);
        $familiesById = [];
        foreach ($families as $family) {
            $familiesById[(string) $family->getId()] = $family;
        }
        /** @var VoiceFamily $skDefault */
        $skDefault = $familiesById[VoiceFamilyFixtures::VOICE_FAMILY_SK_DEFAULT];
        /** @var VoiceFamily $skSecondary */
        $skSecondary = $familiesById[VoiceFamilyFixtures::VOICE_FAMILY_SK_SECONDARY];
        /** @var VoiceFamily $enDefault */
        $enDefault = $familiesById[VoiceFamilyFixtures::VOICE_FAMILY_EN_DEFAULT];

        yield (new Voice())
            ->setId(self::VOICE_SK_DEFAULT_GOOGLE)
            ->setVoiceFamily($skDefault)
            ->setProvider(TtsProvider::GoogleTts)
            ->setExternalVoiceId('sk-SK-Standard-A')
            ->setMetadata(['gender' => 'female', 'naturalSampleRateHertz' => 24_000])
            ->setMain(true)
            ->setActive(true)
        ;

        yield (new Voice())
            ->setId(self::VOICE_SK_DEFAULT_ELEVENLABS)
            ->setVoiceFamily($skDefault)
            ->setProvider(TtsProvider::Elevenlabs)
            ->setExternalVoiceId('21m00Tcm4TlvDq8ikWAM')
            ->setMetadata(['gender' => 'female', 'modelId' => 'eleven_multilingual_v2'])
            ->setMain(false)
            ->setActive(true)
        ;

        yield (new Voice())
            ->setId(self::VOICE_SK_SECONDARY_ELEVENLABS)
            ->setVoiceFamily($skSecondary)
            ->setProvider(TtsProvider::Elevenlabs)
            ->setExternalVoiceId('AZnzlk1XvdvUeBnXmlld')
            ->setMetadata(['gender' => 'male', 'modelId' => 'eleven_multilingual_v2'])
            ->setMain(true)
            ->setActive(true)
        ;

        yield (new Voice())
            ->setId(self::VOICE_EN_DEFAULT_ELEVENLABS)
            ->setVoiceFamily($enDefault)
            ->setProvider(TtsProvider::Elevenlabs)
            ->setExternalVoiceId('EXAVITQu4vr4xnSDxMaL')
            ->setMetadata(['gender' => 'female', 'modelId' => 'eleven_multilingual_v2'])
            ->setMain(true)
            ->setActive(true)
        ;

        yield (new Voice())
            ->setId(self::VOICE_EN_DEFAULT_GOOGLE)
            ->setVoiceFamily($enDefault)
            ->setProvider(TtsProvider::GoogleTts)
            ->setExternalVoiceId('en-US-Standard-C')
            ->setMetadata(['gender' => 'female', 'naturalSampleRateHertz' => 24_000])
            ->setMain(false)
            ->setActive(false)
        ;
    }
}
