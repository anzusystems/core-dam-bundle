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
 * Seeds stuck-TTS scenarios for {@see TtsCleanupStuckCommandTest}: one past the hard cap (→ Failed), one within (→ Done).
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
