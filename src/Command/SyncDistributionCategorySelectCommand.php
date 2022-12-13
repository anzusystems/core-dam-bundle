<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect\DistributionCategorySelectSynchronizer;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:distribution:sync-category-select',
    description: 'Synchronize distribute category selects according configuration for each ExtSystem.'
)]
final class SyncDistributionCategorySelectCommand extends Command
{
    use OutputUtilTrait;

    public function __construct(
        private readonly DistributionCategorySelectSynchronizer $distributionCategorySelectSynchronizer,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->outputUtil->setOutput($output);
        $this->distributionCategorySelectSynchronizer->synchronizeForAllExtSystems();

        return self::SUCCESS;
    }
}
