<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileUsageReconciler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'anzu-dam:asset-file:reconcile-usage',
    description: 'Release single use photos the ext system no longer points at, claim by claim as they come due'
)]
final readonly class AssetFileReconcileUsageCommand
{
    private const int DEFAULT_LIMIT = 10_000;

    public function __construct(
        private AssetFileUsageReconciler $assetFileUsageReconciler,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'How many due asset files to check in this run')]
        int $limit = self::DEFAULT_LIMIT,
    ): int {
        $result = $this->assetFileUsageReconciler->reconcile($limit);

        $io->success(sprintf(
            'Checked %d asset file(s): %d group(s) confirmed, %d released, %d unanswered, %d left to the next run',
            $result->getChecked(),
            $result->getConfirmed(),
            $result->getReleased(),
            $result->getUnanswered(),
            $result->getSkipped(),
        ));

        return Command::SUCCESS;
    }
}
