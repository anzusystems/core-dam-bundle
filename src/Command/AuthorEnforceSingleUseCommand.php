<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseFacade;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:author:enforce-single-use',
    description: 'Switch every asset file of assets with a source with flags.singleUseEnforced to single use and reindex it'
)]
final class AuthorEnforceSingleUseCommand extends Command
{
    private const string ARG_AUTHOR_ID = 'author_id';

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly AssetFileSingleUseFacade $assetFileSingleUseFacade,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument(
            name: self::ARG_AUTHOR_ID,
            mode: InputArgument::REQUIRED,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->authorRepository->find((string) $input->getArgument(self::ARG_AUTHOR_ID));
        if (null === $source) {
            $output->writeln('<error>Author not found</error>');

            return Command::FAILURE;
        }
        if (false === $source->getFlags()->isSource() || false === $source->getFlags()->isSingleUseEnforced()) {
            $output->writeln('<error>Author is not a source that enforces single use, nothing to do</error>');

            return Command::FAILURE;
        }

        $enforcedCount = $this->assetFileSingleUseFacade->enforceForSource($source);
        $output->writeln(sprintf('<info>Switched %d asset file(s) with source %s to single use</info>', $enforcedCount, $source->getName()));

        return Command::SUCCESS;
    }
}
