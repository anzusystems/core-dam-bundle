<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFamilyManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Enum\Language;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsVoiceFixtures;

/** Voice-resolution cascade over real fixtures (cms ext system, `sme_default_male` family, Auto mode). */
final class VoiceResolverTest extends CoreDamKernelTestCase
{
    private VoiceResolver $resolver;
    private VoiceFamilyManager $familyManager;
    private ExtSystem $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->getService(VoiceResolver::class);
        $this->familyManager = $this->getService(VoiceFamilyManager::class);
        $cms = $this->getService(ExtSystemRepository::class)->find(ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->cms = $cms;
    }

    public function testNullSlugResolvesSystemDefaultFamilyVoice(): void
    {
        $voice = $this->resolver->resolve(null, $this->cms);

        self::assertSame(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG, $voice->getVoiceFamily()->getSlug());
        self::assertTrue($voice->isActive());
    }

    public function testUnknownRequestedSlugThrows(): void
    {
        $this->expectException(TtsProviderException::class);
        $this->resolver->resolve('does-not-exist', $this->cms);
    }

    public function testForcedProviderWithoutMatchingVoiceThrows(): void
    {
        $this->cms->getTtsSettings()->setActiveProviderMode(TtsActiveProviderMode::GoogleTts);

        $this->expectException(TtsProviderException::class);
        $this->resolver->resolve(null, $this->cms);
    }

    public function testActiveFamilyWithoutAnyActiveVoiceThrows(): void
    {
        $this->familyManager->create(
            (new VoiceFamily())
                ->setExtSystem($this->cms)
                ->setSlug('empty_family')
                ->setDisplayName('Empty family')
                ->setLanguage(Language::Slovak)
                ->setPreferredProvider(VoiceDiscriminator::Elevenlabs)
                ->setActive(true),
        );

        $this->expectException(TtsProviderException::class);
        $this->resolver->resolve('empty_family', $this->cms);
    }
}
