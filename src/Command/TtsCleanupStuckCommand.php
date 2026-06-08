<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsCancellationFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Helper\DateTimeHelper;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reconcile stalled TTS requests: resume recoverable ones (Waiting/Processing past --older-than),
 * fail/cancel those past --hard-cap. --older-than must exceed one chunk's synth time.
 */
#[AsCommand(
    name: 'anzu-dam:tts:cleanup-stuck',
    description: 'Reconcile stalled TTS requests: resume recoverable ones, fail/cancel those past the hard cap',
)]
final class TtsCleanupStuckCommand extends Command
{
    private const string OPT_OLDER_THAN = 'older-than';
    private const string OPT_HARD_CAP = 'hard-cap';
    private const string OPT_DRY_RUN = 'dry-run';
    private const int BATCH_LIMIT = 200;

    public function __construct(
        private readonly TtsAssetRepository $ttsAssetRepository,
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly TtsSynthesisChunkRepository $chunkRepository,
        private readonly TtsRequestOrchestrator $orchestrator,
        private readonly TtsCancellationFacade $cancelRequest,
        private readonly TtsRequestFailer $requestFailer,
        private readonly MessageBusInterface $messageBus,
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
                'Stale threshold for resume — e.g. 5m, 2m, 10m',
                '5m',
            )
            ->addOption(
                self::OPT_HARD_CAP,
                null,
                InputOption::VALUE_REQUIRED,
                'Give-up threshold — stalled longer than this is failed/cancelled instead of resumed',
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

        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $staleInterval = DateTimeHelper::parseDurationToInterval((string) $input->getOption(self::OPT_OLDER_THAN));
        $hardCapInterval = DateTimeHelper::parseDurationToInterval((string) $input->getOption(self::OPT_HARD_CAP));
        if (null === $staleInterval || null === $hardCapInterval) {
            $output->writeln('<error>Cannot parse duration. Use e.g. 5m, 1h, 2h30m.</error>');

            return Command::FAILURE;
        }

        $now = new DateTimeImmutable();
        $staleBefore = $now->sub($staleInterval);
        $hardCapBefore = $now->sub($hardCapInterval);

        $regenCount = $this->cancelStuckRegens($hardCapBefore, $dryRun, $output);
        $waitingCount = $this->reconcileWaiting($staleBefore, $hardCapBefore, $dryRun, $output);
        $processingCount = $this->reconcileProcessing($staleBefore, $hardCapBefore, $dryRun, $output);

        $output->writeln(sprintf(
            '%s: %d regen-cancelled + %d waiting + %d processing request(s) reconciled%s.',
            $dryRun ? 'DRY RUN' : 'DONE',
            $regenCount,
            $waitingCount,
            $processingCount,
            $dryRun ? ' (no changes made)' : App::EMPTY_STRING,
        ));

        return Command::SUCCESS;
    }

    /**
     * Past hard cap or failed chunk → fail; otherwise resume (re-dispatch / re-arm / finalize).
     */
    private function reconcileProcessing(
        DateTimeImmutable $staleBefore,
        DateTimeImmutable $hardCapBefore,
        bool $dryRun,
        OutputInterface $output,
    ): int {
        $count = 0;
        foreach ($this->requestRepository->findStalledProcessing($staleBefore, self::BATCH_LIMIT) as $request) {
            $requestId = (string) $request->getId();

            // Hard cap from last real progress (latest chunk activity, or startedAt for inline path).
            $stalledSince = $this->chunkRepository->findLastChunkActivity($requestId) ?? $request->getStartedAt();
            if (null !== $stalledSince && $stalledSince < $hardCapBefore) {
                if (false === $dryRun) {
                    $this->requestFailer->fail($request, 'Processing stalled beyond hard cap.');
                }
                $output->writeln(sprintf('%s processing request %s (hard cap)', $dryRun ? '[dry-run] Would fail' : 'Failed', $requestId));
                ++$count;

                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf('[dry-run] Would resume processing request %s', $requestId));
                ++$count;

                continue;
            }

            $result = $this->orchestrator->resumeStalled($request, $staleBefore);
            if ($result->isUnrecoverable()) {
                $this->requestFailer->fail($request, sprintf('Unrecoverable stalled request (%s).', $result->value));
            }
            $output->writeln(sprintf('Reconciled processing request %s: %s', $requestId, $result->value));
            ++$count;
        }

        return $count;
    }

    /**
     * Dispatch message lost; past hard cap → fail (frees idempotency key), otherwise re-dispatch.
     */
    private function reconcileWaiting(
        DateTimeImmutable $staleBefore,
        DateTimeImmutable $hardCapBefore,
        bool $dryRun,
        OutputInterface $output,
    ): int {
        $count = 0;
        foreach ($this->requestRepository->findStuckWaiting($staleBefore, self::BATCH_LIMIT) as $request) {
            $requestId = (string) $request->getId();
            // Uses modifiedAt — startedAt is only set at Processing.
            $pastHardCap = $request->getModifiedAt() < $hardCapBefore;

            if (false === $dryRun && $pastHardCap) {
                $this->requestFailer->fail($request, 'Stuck in waiting beyond hard cap (dispatch lost).');
            }
            if (false === $dryRun && false === $pastHardCap) {
                $this->messageBus->dispatch(new TtsNarrationRequestMessage($requestId));
            }
            $output->writeln(sprintf(
                '%s waiting request %s',
                $dryRun ? '[dry-run] Would reconcile' : ($pastHardCap ? 'Failed' : 'Re-dispatched'),
                $requestId,
            ));
            ++$count;
        }

        return $count;
    }

    /**
     * Regen stuck in 'superseding' past hard cap → cancel (old audio remains valid).
     */
    private function cancelStuckRegens(DateTimeImmutable $hardCapBefore, bool $dryRun, OutputInterface $output): int
    {
        $ttsAssets = $this->ttsAssetRepository->findStuckSuperseding($hardCapBefore, self::BATCH_LIMIT);

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
}
