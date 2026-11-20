<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseFacade;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:enforce-single-use',
    description: 'Switch every asset file of assets with a source with flags.singleUseEnforced to single use'
)]
final readonly class AuthorEnforceSingleUseCommand
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private AssetSourceDBALRepository $assetSourceDBALRepository,
        private AssetFileSingleUseFacade $assetFileSingleUseFacade,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Id of the source', name: 'author_id')]
        string $authorId,
        #[Option(description: 'Write nothing, only count the asset files to switch')]
        bool $dryRun = false,
    ): int {
        $source = $this->authorRepository->find($authorId);
        if (null === $source) {
            $io->error(sprintf('Author %s not found', $authorId));

            return Command::FAILURE;
        }
        if (false === $source->getFlags()->isSource() || false === $source->getFlags()->isSingleUseEnforced()) {
            $io->error('Author is not a source that enforces single use, nothing to do');

            return Command::FAILURE;
        }

        $progressBar = $io->createProgressBar($this->assetSourceDBALRepository->countAssetsOfSource($authorId));
        $progressBar->setFormat('debug');
        $switched = 0;
        foreach ($this->assetFileSingleUseFacade->enforceForSource($source, $dryRun) as $checked => $batchSwitched) {
            $progressBar->advance($checked);
            $switched += $batchSwitched;
        }
        $progressBar->finish();
        $io->newLine(2);
        if ($dryRun) {
            $io->success(sprintf('Dry run, nothing written: %d asset file(s) with source %s to switch to single use', $switched, $source->getName()));

            return Command::SUCCESS;
        }

        $io->success([
            sprintf('Switched %d asset file(s) with source %s to single use', $switched, $source->getName()),
            sprintf('The search index is not updated: run anzu-dam:elastic:rebuild asset --ext-system=%s --no-drop', $source->getExtSystem()->getSlug()),
            'The ext system is not notified: create a synchronize image changed job of each licence of these assets',
        ]);

        return Command::SUCCESS;
    }
}
