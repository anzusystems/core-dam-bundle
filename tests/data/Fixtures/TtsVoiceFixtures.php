<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFamilyManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceManager;
use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\Language;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Generator;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Seeds the system-default voice family (slug matches {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Config}
 * test wiring `sme_default_male`) with a single main ElevenLabs voice under the cms ext system. The
 * ElevenLabs HTTP client is mocked in tests, so the external voice id is arbitrary.
 *
 * @extends AbstractFixtures<VoiceFamily>
 */
final class TtsVoiceFixtures extends AbstractFixtures
{
    public const string DEFAULT_FAMILY_SLUG = 'sme_default_male';
    public const string ELEVENLABS_VOICE_ID = 'test-elevenlabs-voice';

    public function __construct(
        private readonly VoiceFamilyManager $voiceFamilyManager,
        private readonly VoiceManager $voiceManager,
        private readonly ExtSystemRepository $extSystemRepository,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return VoiceFamily::class;
    }

    public static function getDependencies(): array
    {
        return [ExtSystemFixtures::class];
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var array{family: VoiceFamily, voice: ElevenlabsVoice} $row */
        foreach ($progressBar->iterate($this->getData()) as $row) {
            $this->voiceFamilyManager->create($row['family'], false);
            $this->voiceManager->create($row['voice'], false);
            $this->addToRegistry($row['family'], $row['family']->getSlug());
        }
        $this->voiceFamilyManager->flush();
    }

    /**
     * @return Generator<int, array{family: VoiceFamily, voice: ElevenlabsVoice}>
     */
    private function getData(): Generator
    {
        $cms = $this->extSystemRepository->find(ExtSystemFixtures::ID_CMS)
            ?? throw new RuntimeException('TtsVoiceFixtures: cms ext system not found — ExtSystemFixtures must run first.');

        $family = (new VoiceFamily())
            ->setExtSystem($cms)
            ->setSlug(self::DEFAULT_FAMILY_SLUG)
            ->setDisplayName('SME Default Male')
            ->setLanguage(Language::Slovak)
            ->setPreferredProvider(VoiceDiscriminator::Elevenlabs)
            ->setActive(true)
        ;

        $voice = (new ElevenlabsVoice())
            ->setVoiceFamily($family)
            ->setExternalVoiceId(self::ELEVENLABS_VOICE_ID)
            ->setMain(true)
            ->setActive(true)
        ;

        yield ['family' => $family, 'voice' => $voice];
    }
}
