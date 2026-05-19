<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Voice\ResolvedVoice;
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
     * Cascade: requested family → system default → preferred provider → primary voice in family.
     * When ExtSystem has a forced provider mode (Elevenlabs/GoogleTts), only that provider is tried;
     * no fallback cascade — throws immediately if no matching voice exists.
     *
     * @throws TtsProviderException
     */
    public function resolve(?string $familySlug, ExtSystem $extSystem): ResolvedVoice
    {
        $targetSlug = $familySlug ?? $this->config->getSystemDefaultFamilySlug();
        $family = $this->resolveFamily($targetSlug, $extSystem);
        $mode = $extSystem->getTtsSettings()->getActiveProviderMode();
        $voice = $this->resolveVoice($family, $mode, $extSystem);

        return new ResolvedVoice(
            voiceFamilyId: (string) $family->getId(),
            voiceFamilySlug: $family->getSlug(),
            provider: $voice->getProvider(),
            externalVoiceId: $voice->getExternalVoiceId(),
            metadata: $voice->getMetadata(),
        );
    }

    /**
     * @throws TtsProviderException if no active family resolves
     */
    private function resolveFamily(string $targetSlug, ExtSystem $extSystem): VoiceFamily
    {
        $family = $this->familyRepo->findOneBySlug($targetSlug, $extSystem);
        $defaultSlug = $this->config->getSystemDefaultFamilySlug();

        if ((null === $family || false === $family->isActive()) && $targetSlug !== $defaultSlug) {
            $family = $this->familyRepo->findOneBySlug($defaultSlug, $extSystem);
        }

        if (null === $family || false === $family->isActive()) {
            // Misconfigured tenant — surface the default-family gap so ops can fix it.
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'voiceResolver.defaultFamilyMissing', [
                'defaultSlug' => $defaultSlug,
                'extSystem' => $extSystem->getSlug(),
            ]);

            throw new TtsProviderException(sprintf(
                'No active VoiceFamily resolved for ext system "%s" (requested slug "%s", default "%s").',
                $extSystem->getSlug(),
                $targetSlug,
                $defaultSlug,
            ));
        }

        return $family;
    }

    /**
     * @throws TtsProviderException
     */
    private function resolveVoice(VoiceFamily $family, TtsActiveProviderMode $mode, ExtSystem $extSystem): Voice
    {
        $forcedProvider = $mode->toProvider();

        if (null !== $forcedProvider) {
            $voice = $this->voiceRepo->findOneActiveByFamilyAndProvider($family, $forcedProvider);
            if (null !== $voice) {
                return $voice;
            }

            throw new TtsProviderException(sprintf(
                'VoiceFamily "%s" has no active voice for forced provider "%s" on ExtSystem "%s" (mode=forced).',
                $family->getSlug(),
                $forcedProvider->value,
                $extSystem->getSlug(),
            ));
        }

        $preferredProvider = $family->getPreferredProvider();
        if (null !== $preferredProvider) {
            $voice = $this->voiceRepo->findOneActiveByFamilyAndProvider($family, $preferredProvider);
            if (null !== $voice) {
                return $voice;
            }
        }

        $voice = $this->voiceRepo->findOnePrimaryActiveByFamily($family);
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
