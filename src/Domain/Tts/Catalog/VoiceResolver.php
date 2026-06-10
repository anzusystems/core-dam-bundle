<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;
use AnzuSystems\CoreDamBundle\Repository\VoiceRepository;

final readonly class VoiceResolver
{
    public function __construct(
        private VoiceFamilyRepository $familyRepo,
        private VoiceRepository $voiceRepo,
        private Config $config,
        private DamLogger $logger,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    public function resolve(?string $familySlug, ExtSystem $extSystem): Voice
    {
        $family = $this->resolveFamily($familySlug, $extSystem);
        $mode = $extSystem->getTtsSettings()->getActiveProviderMode();

        return $this->resolveVoice($family, $mode, $extSystem);
    }

    /**
     * @throws TtsProviderException if no active family resolves
     */
    private function resolveFamily(?string $familySlug, ExtSystem $extSystem): VoiceFamily
    {
        // Explicit slug must resolve to an active family — never silently substitute another voice.
        if (null !== $familySlug) {
            $family = $this->familyRepo->findOneBySlug($familySlug, $extSystem);
            if (null === $family || false === $family->isActive()) {
                throw new TtsProviderException(sprintf(
                    'Requested VoiceFamily "%s" is missing or inactive for ext system "%s".',
                    $familySlug,
                    $extSystem->getSlug(),
                ));
            }

            return $family;
        }

        // No requested family → resolve from DAM config: ext-system default, default slug, then any active family.
        $defaultSlug = $this->config->getSystemDefaultFamilySlug();
        $family = $this->resolveExtSystemDefaultFamily($extSystem)
            ?? $this->familyRepo->findOneBySlug($defaultSlug, $extSystem)
            ?? $this->familyRepo->findOneActiveByExtSystem($extSystem);

        if (null === $family || false === $family->isActive()) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'voiceResolver.defaultFamilyMissing', [
                'defaultSlug' => $defaultSlug,
                'extSystem' => $extSystem->getSlug(),
            ]);

            throw new TtsProviderException(sprintf(
                'No active VoiceFamily resolved for ext system "%s" (default "%s").',
                $extSystem->getSlug(),
                $defaultSlug,
            ));
        }

        return $family;
    }

    private function resolveExtSystemDefaultFamily(ExtSystem $extSystem): ?VoiceFamily
    {
        $defaultId = $extSystem->getTtsSettings()->getDefaultVoiceFamilyId();
        if (null === $defaultId) {
            return null;
        }

        $family = $this->familyRepo->find($defaultId);
        if (null === $family || $family->getExtSystem()->isNot($extSystem) || false === $family->isActive()) {
            return null;
        }

        return $family;
    }

    /**
     * @throws TtsProviderException
     */
    private function resolveVoice(VoiceFamily $family, TtsActiveProviderMode $mode, ExtSystem $extSystem): Voice
    {
        $forcedDiscriminator = $mode->toProvider();

        if (null !== $forcedDiscriminator) {
            $voice = $this->voiceRepo->findOneActiveByFamilyAndDiscriminator($family, $forcedDiscriminator);
            if (null !== $voice) {
                return $voice;
            }

            throw new TtsProviderException(sprintf(
                'VoiceFamily "%s" has no active voice for forced provider "%s" on ExtSystem "%s" (mode=forced).',
                $family->getSlug(),
                $forcedDiscriminator->value,
                $extSystem->getSlug(),
            ));
        }

        $preferredDiscriminator = $family->getPreferredProvider();
        if (null !== $preferredDiscriminator) {
            $voice = $this->voiceRepo->findOneActiveByFamilyAndDiscriminator($family, $preferredDiscriminator);
            if (null !== $voice) {
                return $voice;
            }
        }

        // Fall back to any active voice so a single-voice family works without a main flag.
        $voice = $this->voiceRepo->findOnePrimaryActiveByFamily($family)
            ?? $this->voiceRepo->findOneActiveByFamily($family);
        if (null !== $voice) {
            return $voice;
        }

        throw new TtsProviderException(sprintf(
            'No active voice found for family "%s" and ext system "%s". '
            . 'Please configure at least one active Voice binding.',
            $family->getSlug(),
            $extSystem->getSlug(),
        ));
    }
}
