<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileGroupUsage;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileUsageReconcileResult;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileUsageReconcileOutcome;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Keeps the register in step with the ext system that owns the licence: the ext system is the truth about
 * what uses a photo, DAM is the register that makes it exclusive. A group nothing points at is freed, and
 * a group held by somebody else than DAM recorded is corrected — a claim that was accepted and then rolled
 * back, or a hand over DAM never heard about, settles here. The whole take-over group is asked about at
 * once, because a copy is what the ext system points at once a photo was taken over.
 */
final readonly class AssetFileUsageReconciler
{
    private const int PAGE_SIZE = 100;

    /**
     * A group still in use, or one the ext system did not answer for, is asked about again a day later
     * rather than dropping out of the check for good: a holder has to stay verifiable for as long as it
     * is held, and an hourly question about an unchanged photo would ask 24 times a day.
     */
    private const string RECHECK_DELAY = 'P1D';

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileManager $assetFileManager,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function reconcile(int $limit): AssetFileUsageReconcileResult
    {
        $now = App::getAppDate();
        $idFrom = App::EMPTY_STRING;
        $checked = App::ZERO;
        $confirmed = App::ZERO;
        $rewritten = App::ZERO;
        $released = App::ZERO;
        $skipped = App::ZERO;
        $unanswered = App::ZERO;

        while ($checked < $limit) {
            $due = $this->assetFileRepository->findUsageChecksDue($now, min(self::PAGE_SIZE, $limit - $checked), $idFrom);
            if ([] === $due) {
                break;
            }

            $idFrom = (string) $due[array_key_last($due)]->getId();
            $checked += count($due);

            foreach ($this->usageByRoot($due) as $rootId => $usage) {
                $outcome = $this->settleGroup($rootId, $usage, $now);
                match ($outcome) {
                    AssetFileUsageReconcileOutcome::Skipped => $skipped++,
                    AssetFileUsageReconcileOutcome::Unanswered => $unanswered++,
                    AssetFileUsageReconcileOutcome::Released => $released++,
                    AssetFileUsageReconcileOutcome::Rewritten => $rewritten++,
                    AssetFileUsageReconcileOutcome::Confirmed => $confirmed++,
                };
            }

            // Every page fetches its own rows fresh in the next iteration, so nothing here is needed past
            // the page boundary — up to PAGE_SIZE take-over groups otherwise stay in the identity map for
            // the whole run.
            $this->assetFileManager->clear();
        }

        if (App::ZERO < $unanswered) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('Single use usage reconcile kept %d group(s) held: the ext system did not answer', $unanswered),
            );
        }

        return new AssetFileUsageReconcileResult($checked, $confirmed, $rewritten, $released, $skipped, $unanswered);
    }

    /**
     * Used beats unanswered beats unused: one file the ext system still points at holds the whole group,
     * and one it did not answer for is enough to leave the group alone. Holders are collected across the
     * group and deduplicated — every file of a take-over group is the same photo.
     *
     * @param list<AssetFile> $due
     *
     * @return array<string, AssetFileGroupUsage>
     */
    private function usageByRoot(array $due): array
    {
        $rootIds = array_values(array_unique(array_map(
            static fn (AssetFile $assetFile): string => $assetFile->getTakeOverRootId(),
            $due,
        )));

        $group = $this->assetFileRepository->findGroupFiles($rootIds);
        $imageFiles = array_filter($group, static fn (AssetFile $assetFile): bool => $assetFile instanceof ImageFile);
        $answer = $this->extSystemCallbackFacade->resolveImageFileUsage($imageFiles);

        $usedByRoot = array_fill_keys($rootIds, false);
        $holdersByRoot = array_fill_keys($rootIds, null);
        foreach ($imageFiles as $imageFile) {
            $rootId = $imageFile->getTakeOverRootId();
            $usage = $answer[(string) $imageFile->getId()] ?? null;

            if (null === $usage) {
                $usedByRoot[$rootId] = true === $usedByRoot[$rootId] ? true : null;

                continue;
            }
            if ($usage->isUsed() && true !== $usedByRoot[$rootId]) {
                $usedByRoot[$rootId] = true;
            }
            foreach ($usage->getHolders() ?? [] as $holder) {
                $holdersByRoot[$rootId][$holder->getName() . '/' . $holder->getId()] = $holder;
            }
        }

        $usageByRoot = [];
        foreach ($rootIds as $rootId) {
            $holders = $holdersByRoot[$rootId];
            $usageByRoot[$rootId] = new AssetFileGroupUsage(
                $usedByRoot[$rootId],
                null === $holders ? null : array_values($holders),
            );
        }

        return $usageByRoot;
    }

    /**
     * @throws Throwable
     */
    private function settleGroup(string $rootId, AssetFileGroupUsage $usage, DateTimeImmutable $now): AssetFileUsageReconcileOutcome
    {
        $used = $usage->isUsed();
        $holders = $usage->getHolders();
        $holder = 1 === count($holders ?? []) ? $holders[App::ZERO] : null;

        try {
            $this->assetFileManager->beginTransaction();
            // The same lock a claim and a release take: a claim that lands between the question above and
            // this write pushes its own check date forward, and the group is left to the next run.
            $group = $this->assetFileRepository->findGroupFiles([$rootId], lock: true);
            $stillDue = array_filter($group, static fn (AssetFile $assetFile): bool => self::isDue($assetFile, $now));
            if ([] === $stillDue) {
                $this->assetFileManager->rollback();

                return AssetFileUsageReconcileOutcome::Skipped;
            }

            $attributes = $group[array_key_first($group)]->getAssetAttributes();
            $recordedHolder = $attributes->getUsedByHolderName() . '/' . $attributes->getUsedByHolderId();
            $claim = null === $holder ? null : UsageClaim::fromHolder($holder);
            $rewriteTo = null !== $claim && false === $claim->matches($attributes) ? $claim : null;

            $checkAgainAfter = $now->add(new DateInterval(self::RECHECK_DELAY));
            foreach ($group as $assetFile) {
                if (false === $used) {
                    $this->assetFileManager->updateUsage($assetFile, UsageClaim::released(), flush: false);

                    continue;
                }
                if (null !== $rewriteTo) {
                    $this->assetFileManager->updateUsage($assetFile, $rewriteTo, flush: false);
                }

                // After updateUsage, which arms the short post-claim check: this group was just verified
                // against the ext system, so the next question is due a day from now, not in fifteen minutes.
                $assetFile->getAssetAttributes()->setUsedByCheckAfter($checkAgainAfter);
            }
            $this->assetFileManager->flush();
            $this->assetFileManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetFileManager->isTransactionActive()) {
                $this->assetFileManager->rollback();
            }

            throw $exception;
        }

        return $this->reportOutcome($rootId, $used, $holders, null !== $rewriteTo, $recordedHolder);
    }

    /**
     * @param list<ImageHolderDto>|null $holders
     */
    private function reportOutcome(string $rootId, ?bool $used, ?array $holders, bool $rewrites, string $recordedHolder): AssetFileUsageReconcileOutcome
    {
        if (null === $used) {
            return AssetFileUsageReconcileOutcome::Unanswered;
        }
        if (false === $used) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('Single use group %s released: the ext system points at none of its files', $rootId),
            );

            return AssetFileUsageReconcileOutcome::Released;
        }
        if (null !== $holders && 1 < count($holders)) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf(
                    'Single use group %s is held by %d holders at once, exclusivity is already broken: %s',
                    $rootId,
                    count($holders),
                    implode(', ', array_map(
                        static fn (ImageHolderDto $dto): string => $dto->getName() . '/' . $dto->getId(),
                        $holders,
                    )),
                ),
            );
        }
        if ($rewrites) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('Single use group %s was held by %s, the ext system says otherwise', $rootId, $recordedHolder),
            );

            return AssetFileUsageReconcileOutcome::Rewritten;
        }

        return AssetFileUsageReconcileOutcome::Confirmed;
    }

    private static function isDue(AssetFile $assetFile, DateTimeImmutable $now): bool
    {
        $checkAfter = $assetFile->getAssetAttributes()->getUsedByCheckAfter();

        return $checkAfter instanceof DateTimeImmutable && $checkAfter <= $now;
    }
}
