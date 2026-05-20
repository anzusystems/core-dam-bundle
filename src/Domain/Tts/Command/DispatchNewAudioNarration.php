<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsIdempotencyKey;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchResult;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Idempotent on (extResourceName, extId, extSystem):
 *  - if an active TTS asset already exists for the tuple → {@see DispatchResult::alreadyExists()}
 *  - if another Initial request is in flight (openInitialKey UNIQUE collision) → {@see DispatchResult::alreadyPending()}
 *  - otherwise → persist new request, dispatch to Messenger, return {@see DispatchResult::pending()}
 */
final readonly class DispatchNewAudioNarration
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private TtsNarrationRequestManager $requestManager,
        private VoiceResolver $voiceResolver,
        private TtsProviderContainer $providerContainer,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param bool $dispatch When false, the request is persisted but the Messenger message is not
     *                       dispatched — caller becomes responsible for running the orchestrator
     *                       (used by the sync test command).
     *
     * @throws ImmutableAudioNarrationException if the both-null / both-non-null invariant is violated
     * @throws ValidationException              if voice resolution or provider precheck fails
     *                                          (mapped to per-field errors → admin shows snackbar / form-field highlight)
     */
    public function execute(TtsSynthesizeRequestDto $dto, AssetLicence $licence, bool $dispatch = true): DispatchResult
    {
        App::throwOnReadOnlyMode();

        $extResourceName = $dto->getExtResourceName();
        $extId = $dto->getExtId();
        if ((null === $extResourceName) !== (null === $extId)) {
            throw new ImmutableAudioNarrationException(
                'extResourceName and extId must be both null or both non-null.',
            );
        }

        $extSystem = $licence->getExtSystem();

        // Idempotency short-circuit runs before voice resolution — no point paying voice-DB cost
        // when we'll return an existing assetId.
        $existing = $this->findExistingForExtTuple($extResourceName, $extId, $extSystem);
        if (null !== $existing) {
            return $existing;
        }

        // Fail-fast: voice resolution + provider config check (deterministic, no HTTP). Surfaces
        // as 422 ValidationException so admin uses the same alert/field-highlight flow as any
        // other form validation error — instead of a raw 503 from the runtime exception handler.
        $voice = $this->resolveVoiceOrThrowValidation($dto->getVoiceFamilySlug(), $extSystem);
        $this->precheckProviderOrThrowValidation($voice, $extSystem);

        $openInitialKey = TtsIdempotencyKey::forInitial($extResourceName, $extId, $extSystem);

        $result = $this->entityManager->wrapInTransaction(
            fn (): DispatchResult => $this->persistOrAlreadyPending($this->buildInitialRequest($dto, $licence, $extSystem, $openInitialKey)),
        );

        if ($dispatch && null !== $result->requestId) {
            $this->messageBus->dispatch(new TtsNarrationRequestMessage($result->requestId));
        }

        return $result;
    }

    /**
     * @throws ValidationException
     */
    private function resolveVoiceOrThrowValidation(?string $voiceFamilySlug, ExtSystem $extSystem): Voice
    {
        try {
            return $this->voiceResolver->resolve($voiceFamilySlug, $extSystem);
        } catch (TtsProviderException) {
            // Underlying exception is logged via the contextId chain — admin gets a translatable
            // field error code, ops correlate via contextId.
            throw (new ValidationException())->addFormattedError('voiceFamilySlug', ValidationException::ERROR_FIELD_INVALID);
        }
    }

    /**
     * @throws ValidationException
     */
    private function precheckProviderOrThrowValidation(Voice $voice, ExtSystem $extSystem): void
    {
        try {
            $this->providerContainer->forDiscriminator($voice->getDiscriminator())->precheck($voice, $extSystem);
        } catch (TtsProviderException) {
            // Surfaces under assetLicence because the failing config is tenant-scoped (licence → extSystem),
            // not a user input field — admin shows it as a top-level form error instead of next to a slug input.
            throw (new ValidationException())->addFormattedError('assetLicence', ValidationException::ERROR_FIELD_INVALID);
        }
    }

    private function findExistingForExtTuple(?string $extResourceName, ?string $extId, ExtSystem $extSystem): ?DispatchResult
    {
        if (null === $extResourceName || null === $extId) {
            return null;
        }

        $existing = $this->ttsAssetRepo->findActiveByExt($extResourceName, $extId, $extSystem);
        if (null === $existing) {
            return null;
        }

        return DispatchResult::alreadyExists((string) $existing->getAsset()->getId());
    }

    private function persistOrAlreadyPending(TtsNarrationRequest $request): DispatchResult
    {
        try {
            $this->requestManager->create($request, false);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return DispatchResult::alreadyPending();
        }

        return DispatchResult::pending((string) $request->getId());
    }

    private function buildInitialRequest(TtsSynthesizeRequestDto $dto, AssetLicence $licence, ExtSystem $extSystem, ?string $openInitialKey): TtsNarrationRequest
    {
        $ttsSettings = $extSystem->getTtsSettings();

        $request = (new TtsNarrationRequest())
            ->setMode(TtsRequestMode::Initial)
            ->setVoiceFamilySlug($dto->getVoiceFamilySlug())
            ->setTitle($dto->getTitle())
            ->setAssetLicenceId($licence->getId())
            ->setOpenInitialKey($openInitialKey);

        $request->getExtRef()
            ->setExtResourceName($dto->getExtResourceName())
            ->setExtId($dto->getExtId());

        $request->getSource()
            ->setText($dto->getText())
            ->setHash(hash('sha256', $dto->getText()));

        $request->getPodcastOptions()
            ->setAutoPodcastId($ttsSettings->getAutoPodcastId())
            ->setRecommendedPodcastId($ttsSettings->getRecommendedPodcastId())
            ->setIncludeInRecommended($dto->isIncludeInRecommendedPodcast());

        return $request;
    }
}
