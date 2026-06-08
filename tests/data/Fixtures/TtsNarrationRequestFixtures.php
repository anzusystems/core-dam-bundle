<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Stuck-TTS scenarios for {@see TtsCleanupStuckCommandTest}. Requests are created through the real dispatch
 * facade (so each owns a reserved asset + a valid idempotency key) and then backdated to simulate a lost
 * dispatch message — instead of mutating the DB from the test itself:
 *  - {@see FAIL_TEXT}: abandoned in Waiting past the hard cap → the cron fails it (frees the idempotency key);
 *  - {@see RESUME_TEXT}: abandoned in Waiting but still within the hard cap → the cron re-dispatches the plan
 *    and, with the sync transport + mocked provider, the synthesis completes, so the request recovers to Done.
 *
 * @extends AbstractFixtures<TtsNarrationRequest>
 */
final class TtsNarrationRequestFixtures extends AbstractFixtures
{
    public const string FAIL_TEXT = 'Cleanup fixture: a narration abandoned in waiting past the hard cap.';
    public const string RESUME_TEXT = 'Cleanup fixture: a narration whose dispatch was lost but is still recoverable.';

    public function __construct(
        private readonly TtsDispatchFacade $dispatchFacade,
        private readonly AssetLicenceRepository $assetLicenceRepository,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return TtsNarrationRequest::class;
    }

    public static function getDependencies(): array
    {
        return [BaseAssetLicenceFixtures::class, TtsVoiceFixtures::class];
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AssetLicence $licence */
        $licence = $this->assetLicenceRepository->find(BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);

        // [text, createdAt, modifiedAt]: FAIL_TEXT is past the hard cap (both old) → the cron gives up;
        // RESUME_TEXT is stale enough to be picked up but young enough to recover (within the hard cap).
        $scenarios = [
            [self::FAIL_TEXT, '-3 hours', '-3 hours'],
            [self::RESUME_TEXT, '-10 minutes', '-2 minutes'],
        ];

        foreach ($progressBar->iterate($scenarios) as [$text, $createdAt, $modifiedAt]) {
            $this->dispatchWaiting($licence, $text)
                ->setCreatedAt(new DateTimeImmutable($createdAt))
                ->setModifiedAt(new DateTimeImmutable($modifiedAt))
            ;
        }

        $this->entityManager->flush();
    }

    private function dispatchWaiting(AssetLicence $licence, string $text): TtsNarrationRequest
    {
        $result = $this->dispatchFacade->synthesize(
            new TtsSynthesizeRequestDto()
                ->setText($text)
                ->setAssetLicence($licence)
                ->setVoiceFamilySlug(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG)
                ->setPodcasts(new ArrayCollection()),
            enqueue: false,
        );

        if (null === $result->narrationRequest) {
            throw new RuntimeException('Fixture dispatch did not produce a narration request.');
        }

        return $result->narrationRequest;
    }
}
