<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
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

/** Idempotent on (licence, sourceTextHash, voiceFamilySlug) — skips provider precheck on existing/in-flight. */
final readonly class TtsDispatchFacade
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private TtsNarrationRequestManager $requestManager,
        private VoiceResolver $voiceResolver,
        private TtsProviderContainer $providerContainer,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private AssetFactory $assetFactory,
        private Validator $validator,
    ) {
    }

    /**
     * @param bool $enqueue false = caller runs the orchestrator synchronously (test command).
     *
     * @throws ValidationException
     */
    public function synthesize(TtsSynthesizeRequestDto $dto, bool $enqueue = true): DispatchResult
    {
        App::throwOnReadOnlyMode();
        $this->validator->validate($dto);

        $licence = $dto->getLicence();
        $extSystem = $licence->getExtSystem();

        $voice = $this->resolveVoiceOrThrowValidation($dto->getVoiceFamilySlug(), $extSystem);

        $sourceTextHash = hash('sha256', $dto->getText());

        $duplicate = $this->findExistingForContent($sourceTextHash, $voice, $licence);
        if (null !== $duplicate) {
            return $duplicate;
        }

        $this->precheckProviderOrThrowValidation($voice, $extSystem);

        $initialIdempotencyKey = TtsIdempotencyKey::forInitial($licence, $sourceTextHash, $dto->getVoiceFamilySlug());

        $result = $this->entityManager->wrapInTransaction(
            fn (): DispatchResult => $this->persistOrAlreadyPending($this->buildInitialRequest($dto, $licence, $initialIdempotencyKey)),
        );

        if ($enqueue && null !== $result->narrationRequest) {
            $this->messageBus->dispatch(new TtsNarrationRequestMessage((string) $result->narrationRequest->getId()));
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
     * Tenant-config failures (missing key, unregistered storage) surface under extSystem field.
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

    private function findExistingForContent(string $sourceTextHash, Voice $voice, AssetLicence $licence): ?DispatchResult
    {
        $existing = $this->ttsAssetRepo->findActiveByContent(
            licence: $licence,
            sourceTextHash: $sourceTextHash,
            voiceFamily: $voice->getVoiceFamily(),
        );
        if (null === $existing) {
            return null;
        }

        return DispatchResult::duplicate((string) $existing->getAsset()->getId());
    }

    private function persistOrAlreadyPending(TtsNarrationRequest $request): DispatchResult
    {
        try {
            $this->requestManager->create(request: $request, flush: false);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Concurrent dispatch won the unique slot; EM is closed after flush exception — no query here.
            return DispatchResult::alreadyPending();
        }

        return DispatchResult::pending((string) $request->getAssetId(), $request);
    }

    private function buildInitialRequest(TtsSynthesizeRequestDto $dto, AssetLicence $licence, string $initialIdempotencyKey): TtsNarrationRequest
    {
        // Shell asset reserves the stable id shared by the CMS placeholder and the final audio; rolled back on unique clash.
        $shellAsset = $this->assetFactory->createAudioShell($licence);

        $request = (new TtsNarrationRequest())
            ->setMode(TtsRequestMode::Initial)
            ->setVoiceFamilySlug($dto->getVoiceFamilySlug())
            ->setTitle($dto->getTitle())
            ->setDescription($dto->getDescription())
            ->setKeywords($dto->getKeywords())
            ->setAuthors($dto->getAuthors())
            ->setExtSystemId($licence->getExtSystem()->getId())
            ->setAssetLicence($licence)
            ->setInitialIdempotencyKey($initialIdempotencyKey)
            ->setAssetId((string) $shellAsset->getId())
            ->setPodcastIds($dto->getPodcasts()->map(static fn (Podcast $podcast): string => (string) $podcast->getId())->toArray())
            ->setSourceText($dto->getText());

        return $request;
    }
}
