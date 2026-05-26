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
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
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
 * Idempotent on (extResourceName, extId, extSystem) — short-circuits on existing active asset or
 * in-flight Initial request before paying the voice/provider precheck cost.
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
     * @param bool $dispatch When false the Messenger message is not dispatched — caller runs the
     *                       orchestrator itself (sync test command).
     *
     * @throws ValidationException
     */
    public function execute(TtsSynthesizeRequestDto $dto, bool $dispatch = true): DispatchResult
    {
        App::throwOnReadOnlyMode();

        $licence = $dto->resolveAssetLicence();
        $extResourceName = $dto->getExtResourceName();
        $extId = $dto->getExtId();
        $extSystem = $licence->getExtSystem();

        $existing = $this->findExistingForExtTuple($extResourceName, $extId, $extSystem);
        if (null !== $existing) {
            return $existing;
        }

        $voice = $this->resolveVoiceOrThrowValidation($dto->getVoiceFamilySlug(), $extSystem);
        $this->precheckProviderOrThrowValidation($voice, $extSystem);

        $openInitialKey = TtsIdempotencyKey::forInitial($extResourceName, $extId, $extSystem);

        $result = $this->entityManager->wrapInTransaction(
            fn (): DispatchResult => $this->persistOrAlreadyPending($this->buildInitialRequest($dto, $licence, $openInitialKey)),
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
            throw (new ValidationException())->addFormattedError('voiceFamilySlug', ValidationException::ERROR_FIELD_INVALID);
        }
    }

    /**
     * Tenant-config failures (missing API key, unregistered storage) surface under `extSystem` —
     * the broken thing is tenant config, not the caller's licence. Raw provider message is
     * forwarded so the admin sees an actionable cause.
     *
     * @throws ValidationException
     */
    private function precheckProviderOrThrowValidation(Voice $voice, ExtSystem $extSystem): void
    {
        try {
            $this->providerContainer->forDiscriminator($voice->getDiscriminator())->precheck($voice, $extSystem);
        } catch (TtsProviderException $e) {
            throw (new ValidationException())->addFormattedError('extSystem', $e->getMessage());
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
            $this->requestManager->create(request: $request, flush: false);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return DispatchResult::alreadyPending();
        }

        return DispatchResult::pending((string) $request->getId());
    }

    private function buildInitialRequest(TtsSynthesizeRequestDto $dto, AssetLicence $licence, ?string $openInitialKey): TtsNarrationRequest
    {
        $request = (new TtsNarrationRequest())
            ->setMode(TtsRequestMode::Initial)
            ->setVoiceFamilySlug($dto->getVoiceFamilySlug())
            ->setTitle($dto->getTitle())
            ->setExtSystemId($licence->getExtSystem()->getId())
            ->setAssetLicenceId($licence->getId())
            ->setOpenInitialKey($openInitialKey)
            ->setPodcastIds($dto->getPodcasts()->map(static fn (Podcast $podcast): string => (string) $podcast->getId())->toArray());

        $request->getExtRef()
            ->setExtResourceName($dto->getExtResourceName())
            ->setExtId($dto->getExtId());

        $request->getSource()
            ->setText($dto->getText());

        return $request;
    }
}
