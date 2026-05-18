<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Messenger\Message\JobAudioNarrationMessage;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\JobAudioNarrationManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsIdempotencyKey;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchResult;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsJobMode;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Idempotent on (extResourceName, extId, extSystem):
 *  - if an active TTS asset already exists for the tuple → {@see DispatchResult::alreadyExists()}
 *  - if another initial job is in flight (openInitialKey UNIQUE collision) → {@see DispatchResult::alreadyPending()}
 *  - otherwise → persist new job, dispatch to Messenger, return {@see DispatchResult::pending()}
 */
final readonly class DispatchNewAudioNarration
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private JobAudioNarrationManager $jobManager,
        private VoiceResolver $voiceResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ImmutableAudioNarrationException if the both-null / both-non-null invariant is violated
     */
    public function execute(TtsSynthesizeRequestDto $dto, AssetLicence $licence): DispatchResult
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

        $this->voiceResolver->resolve($dto->getVoiceFamilySlug(), $extSystem);

        $openInitialKey = TtsIdempotencyKey::forInitial($extResourceName, $extId, $extSystem);

        $result = $this->entityManager->wrapInTransaction(
            fn (): DispatchResult => $this->persistOrAlreadyPending($this->buildInitialJob($dto, $licence, $openInitialKey)),
        );

        if (null !== $result->jobId) {
            $this->messageBus->dispatch(new JobAudioNarrationMessage($result->jobId, TtsJobMode::Initial->value));
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

    private function persistOrAlreadyPending(JobAudioNarration $job): DispatchResult
    {
        try {
            $this->jobManager->create($job, false);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return DispatchResult::alreadyPending();
        }

        return DispatchResult::pending((string) $job->getId());
    }

    private function buildInitialJob(TtsSynthesizeRequestDto $dto, AssetLicence $licence, ?string $openInitialKey): JobAudioNarration
    {
        $job = (new JobAudioNarration())
            ->setMode(TtsJobMode::Initial)
            ->setVoiceFamilySlug($dto->getVoiceFamilySlug())
            ->setTitle($dto->getTitle())
            ->setAssetLicenceId((string) $licence->getId())
            ->setOpenInitialKey($openInitialKey);

        $job->getExtRef()
            ->setExtResourceName($dto->getExtResourceName())
            ->setExtId($dto->getExtId());

        $job->getSource()
            ->setText($dto->getText())
            ->setHash(hash('sha256', $dto->getText()));

        $job->getPodcastOptions()
            ->setIncludeInRecommended($dto->isIncludeInRecommendedPodcast());

        return $job;
    }
}
