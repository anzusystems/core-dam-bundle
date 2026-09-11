<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseEnforcer;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:asset-licence:enforce-single-use',
    description: 'Switch every asset file of a licence with flags.singleUseEnforced to single use and reindex it'
)]
final class AssetLicenceEnforceSingleUseCommand extends Command
{
    private const string ARG_LICENCE_ID = 'licence_id';

    public function __construct(
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument(
            name: self::ARG_LICENCE_ID,
            mode: InputArgument::REQUIRED,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $licence = $this->assetLicenceRepository->find((int) $input->getArgument(self::ARG_LICENCE_ID));
        if (null === $licence) {
            $output->writeln('<error>Asset licence not found</error>');

            return Command::FAILURE;
        }
        if (false === $licence->getFlags()->isSingleUseEnforced()) {
            $output->writeln('<error>Asset licence does not enforce single use, nothing to do</error>');

            return Command::FAILURE;
        }

        $enforcedCount = $this->assetFileSingleUseEnforcer->enforceLicence($licence);
        $output->writeln(sprintf('<info>Switched %d asset file(s) of licence %d to single use</info>', $enforcedCount, $licence->getId()));

        return Command::SUCCESS;
    }
}
