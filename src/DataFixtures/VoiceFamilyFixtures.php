<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFamilyManager;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<VoiceFamily>
 */
final class VoiceFamilyFixtures extends AbstractFixtures
{
    public const string VOICE_FAMILY_SK_DEFAULT = 'a1f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_FAMILY_SK_SECONDARY = 'a2f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_FAMILY_EN_DEFAULT = 'a3f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';
    public const string VOICE_FAMILY_INACTIVE = 'a4f0c6d8-7e3b-4f2a-9c1d-1b2a3c4d5e6f';

    public function __construct(
        private readonly VoiceFamilyManager $voiceFamilyManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['dev', 'test'];
    }

    public static function getIndexKey(): string
    {
        return VoiceFamily::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var VoiceFamily $family */
        foreach ($progressBar->iterate($this->getData()) as $family) {
            $family = $this->voiceFamilyManager->create($family);
            $this->addToRegistry($family, (string) $family->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var ExtSystem $cmsExtSystem */
        $cmsExtSystem = $this->entityManager->find(ExtSystem::class, 1);

        yield (new VoiceFamily())
            ->setId(self::VOICE_FAMILY_SK_DEFAULT)
            ->setExtSystem($cmsExtSystem)
            ->setSlug('sk-default')
            ->setDisplayName('Slovenský hlas (default)')
            ->setLanguage('sk-SK')
            ->setPreferredProvider(TtsProvider::GoogleTts)
            ->setActive(true)
        ;

        yield (new VoiceFamily())
            ->setId(self::VOICE_FAMILY_SK_SECONDARY)
            ->setExtSystem($cmsExtSystem)
            ->setSlug('sk-secondary')
            ->setDisplayName('Slovenský hlas (secondary)')
            ->setLanguage('sk-SK')
            ->setPreferredProvider(TtsProvider::Elevenlabs)
            ->setActive(true)
        ;

        yield (new VoiceFamily())
            ->setId(self::VOICE_FAMILY_EN_DEFAULT)
            ->setExtSystem($cmsExtSystem)
            ->setSlug('en-default')
            ->setDisplayName('English voice (default)')
            ->setLanguage('en-US')
            ->setPreferredProvider(TtsProvider::Elevenlabs)
            ->setActive(true)
        ;

        yield (new VoiceFamily())
            ->setId(self::VOICE_FAMILY_INACTIVE)
            ->setExtSystem($cmsExtSystem)
            ->setSlug('sk-archived')
            ->setDisplayName('Slovenský hlas (archived)')
            ->setLanguage('sk-SK')
            ->setPreferredProvider(null)
            ->setActive(false)
        ;
    }
}
