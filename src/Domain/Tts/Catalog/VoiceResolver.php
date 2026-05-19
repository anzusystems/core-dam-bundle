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
     * Cascade: requested family → system default → preferred provider → primary voice in family.
     * When ExtSystem has a forced provider mode (Elevenlabs/GoogleTts), only that provider is tried;
     * no fallback cascade — throws immediately if no matching voice exists.
     *
     * @throws TtsProviderException
     */
    public function resolve(?string $familySlug, ExtSystem $extSystem): Voice
    {
        $targetSlug = $familySlug ?? $this->config->getSystemDefaultFamilySlug();
        $family = $this->resolveFamily($targetSlug, $extSystem);
        $mode = $extSystem->getTtsSettings()->getActiveProviderMode();

        return $this->resolveVoice($family, $mode, $extSystem);
    }

    /**
     * @throws TtsProviderException if no active family resolves
     */
    private function resolveFamily(string $targetSlug, ExtSystem $extSystem): VoiceFamily
    {
        $family = $this->familyRepo->findOneBySlug($targetSlug, $extSystem);
        $defaultSlug = $this->config->getSystemDefaultFamilySlug();

        if ((null === $family || false === $family->isActive()) && $targetSlug !== $defaultSlug) {
            $family = $this->resolveExtSystemDefaultFamily($extSystem) ?? $this->familyRepo->findOneBySlug($defaultSlug, $extSystem);
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
