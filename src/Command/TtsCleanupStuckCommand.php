<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsCancellationFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Helper\DateTimeHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Recovers TTS requests left stranded by a crashed worker (the claim/synthesis windows run outside one
 * transaction, so an ungraceful kill can't be unwound inline):
 *  - regen stuck in 'superseding' → cancel (the old audio is still valid),
 *  - initial stuck in 'processing' → fail (frees the idempotency key for a fresh dispatch).
 *
 * The threshold must stay well above the worker time-limit so in-flight requests are never touched.
 *
 * Usage:
 *   bin/console anzu-dam:tts:cleanup-stuck
 *   bin/console anzu-dam:tts:cleanup-stuck --older-than=30m
 *   bin/console anzu-dam:tts:cleanup-stuck --older-than=2h --dry-run
 */
#[AsCommand(
    name: 'anzu-dam:tts:cleanup-stuck',
    description: 'Recover TTS requests stuck after a crashed worker (regen superseding, initial processing)',
)]
final class TtsCleanupStuckCommand extends Command
{
    private const string OPT_OLDER_THAN = 'older-than';
    private const string OPT_DRY_RUN = 'dry-run';
    private const int BATCH_LIMIT = 200;

    public function __construct(
        private readonly TtsAssetRepository $ttsAssetRepository,
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly TtsSynthesisChunkRepository $chunkRepository,
        private readonly TtsCancellationFacade $cancelRequest,
        private readonly TtsRequestFailer $requestFailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPT_OLDER_THAN,
                null,
                InputOption::VALUE_REQUIRED,
                'Duration threshold for "stuck" — e.g. 1h, 30m, 2h30m',
                '1h',
            )
            ->addOption(
                self::OPT_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Print matched requests without changing them',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        App::throwOnReadOnlyMode();

        $olderThanStr = (string) $input->getOption(self::OPT_OLDER_THAN);
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);

        $interval = DateTimeHelper::parseDurationToInterval($olderThanStr);
        if (null === $interval) {
            $output->writeln(sprintf('<error>Cannot parse duration "%s". Use e.g. 1h, 30m, 2h30m.</error>', $olderThanStr));

            return Command::FAILURE;
        }

        $threshold = (new DateTimeImmutable())->sub($interval);

        $regenCount = $this->cleanupStuckRegens($threshold, $dryRun, $output);
        $initialCount = $this->cleanupStuckInitials($threshold, $dryRun, $output);
        $waitingCount = $this->cleanupStuckWaiting($threshold, $dryRun, $output);
        $chunkCount = $this->cleanupStuckChunks($threshold, $dryRun, $output);

        $output->writeln(sprintf(
            '%s: %d regen + %d initial + %d waiting + %d chunk-stalled request(s) processed%s.',
            $dryRun ? 'DRY RUN' : 'DONE',
            $regenCount,
            $initialCount,
            $waitingCount,
            $chunkCount,
            $dryRun ? ' (no changes made)' : App::EMPTY_STRING,
        ));

        return Command::SUCCESS;
    }

    /**
     * Requests stuck in Waiting (dispatch/plan message lost — at-most-once transport) → fail.
     */
    private function cleanupStuckWaiting(DateTimeImmutable $threshold, bool $dryRun, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->requestRepository->findStuckWaiting($threshold, self::BATCH_LIMIT) as $request) {
            if (false === $dryRun) {
                $this->requestFailer->fail($request, 'Stuck in waiting beyond cleanup threshold (dispatch lost).');
            }
            $output->writeln(sprintf(
                '%s waiting request %s',
                $dryRun ? '[dry-run] Would fail' : 'Failed',
                (string) $request->getId(),
            ));
            ++$count;
        }

        return $count;
    }

    /**
     * Multi-chunk requests with a chunk stuck Pending (chain dispatch lost) or Processing (worker crashed
     * mid-synth) → fail the parent request once. No resume — the caller re-dispatches.
     */
    private function cleanupStuckChunks(DateTimeImmutable $threshold, bool $dryRun, OutputInterface $output): int
    {
        /** @var array<string, TtsNarrationRequest> $stalled */
        $stalled = [];
        $stuckBatches = [
            $this->chunkRepository->findStuckPending($threshold, self::BATCH_LIMIT),
            $this->chunkRepository->findStuckProcessing($threshold, self::BATCH_LIMIT),
        ];
        foreach ($stuckBatches as $stuckChunks) {
            foreach ($stuckChunks as $chunk) {
                $request = $chunk->getRequest();
                if ($request->getStatus()->in(TtsRequestStatus::TERMINAL_STATUSES)) {
                    continue;
                }
                $stalled[(string) $request->getId()] = $request;
            }
        }

        foreach ($stalled as $requestId => $request) {
            if (false === $dryRun) {
                $this->requestFailer->fail($request, 'A synthesis chunk stalled beyond cleanup threshold.');
            }
            $output->writeln(sprintf(
                '%s chunk-stalled request %s',
                $dryRun ? '[dry-run] Would fail' : 'Failed',
                $requestId,
            ));
        }

        return count($stalled);
    }

    private function cleanupStuckRegens(DateTimeImmutable $threshold, bool $dryRun, OutputInterface $output): int
    {
        $ttsAssets = $this->ttsAssetRepository->findStuckSuperseding($threshold, self::BATCH_LIMIT);

        $stableAssetIds = [];
        foreach ($ttsAssets as $ttsAsset) {
            $stableAssetIds[] = (string) $ttsAsset->getAsset()->getId();
        }
        $regensByStable = $this->requestRepository->findActiveRegensForStables($stableAssetIds);

        $count = 0;
        foreach ($stableAssetIds as $assetId) {
            $activeRegen = $regensByStable[$assetId] ?? null;
            if (null === $activeRegen) {
                $output->writeln(sprintf('Asset %s is stuck in superseding without an active regen request — skipping.', $assetId));

                continue;
            }

            $requestId = (string) $activeRegen->getId();
            if (false === $dryRun) {
                $this->cancelRequest->cancel($activeRegen, null);
            }
            $output->writeln(sprintf(
                '%s regen request %s for asset %s',
                $dryRun ? '[dry-run] Would cancel' : 'Cancelled',
                $requestId,
                $assetId,
            ));
            ++$count;
        }

        return $count;
    }

    private function cleanupStuckInitials(DateTimeImmutable $threshold, bool $dryRun, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->requestRepository->findStuckInitialProcessing($threshold, self::BATCH_LIMIT) as $request) {
            $requestId = (string) $request->getId();
            if (false === $dryRun) {
                $this->requestFailer->fail($request, 'Stuck in processing beyond cleanup threshold.');
            }
            $output->writeln(sprintf(
                '%s initial request %s (asset %s)',
                $dryRun ? '[dry-run] Would fail' : 'Failed',
                $requestId,
                (string) $request->getAssetId(),
            ));
            ++$count;
        }

        return $count;
    }
}
