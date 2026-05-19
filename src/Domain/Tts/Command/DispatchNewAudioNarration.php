<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsIdempotencyKey;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
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

        // Fail-fast validation: ensures the slug + ExtSystem combination yields a usable voice
        // before we persist a request. Result is discarded — handler re-resolves at processing time
        // to pick the freshest voice (active flag may have flipped since dispatch).
        $this->voiceResolver->resolve($dto->getVoiceFamilySlug(), $extSystem);

        $openInitialKey = TtsIdempotencyKey::forInitial($extResourceName, $extId, $extSystem);

        $result = $this->entityManager->wrapInTransaction(
            fn (): DispatchResult => $this->persistOrAlreadyPending($this->buildInitialRequest($dto, $licence, $extSystem, $openInitialKey)),
        );

        if ($dispatch && null !== $result->requestId) {
            $this->messageBus->dispatch(new TtsNarrationRequestMessage($result->requestId));
        }

        return $result;
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
