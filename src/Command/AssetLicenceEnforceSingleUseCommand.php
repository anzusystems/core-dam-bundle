<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseFacade;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetFileDBALRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:asset-licence:enforce-single-use',
    description: 'Apply the single use rules to every asset file of a licence and switch the matching ones to single use'
)]
final readonly class AssetLicenceEnforceSingleUseCommand
{
    public function __construct(
        private AssetLicenceRepository $assetLicenceRepository,
        private AssetFileDBALRepository $assetFileDBALRepository,
        private AssetFileSingleUseFacade $assetFileSingleUseFacade,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Id of the licence', name: 'licence_id')]
        int $licenceId,
        #[Option(description: 'Write nothing, only count the asset files to switch')]
        bool $dryRun = false,
    ): int {
        $licence = $this->assetLicenceRepository->find($licenceId);
        if (null === $licence) {
            $io->error(sprintf('Asset licence %d not found', $licenceId));

            return Command::FAILURE;
        }

        $progressBar = $io->createProgressBar($this->assetFileDBALRepository->countNotSingleUseByLicence($licenceId));
        $progressBar->setFormat('debug');
        $progressBar->setBarCharacter('=');
        $progressBar->setEmptyBarCharacter('-');
        $progressBar->setProgressCharacter('>');
        $switched = 0;
        foreach ($this->assetFileSingleUseFacade->enforceForLicence($licence, $dryRun) as $checked => $batchSwitched) {
            $progressBar->advance($checked);
            $switched += $batchSwitched;
        }
        $progressBar->finish();
        $io->newLine(2);
        if ($dryRun) {
            $io->success(sprintf('Dry run, nothing written: %d asset file(s) of licence %d to switch to single use', $switched, $licenceId));

            return Command::SUCCESS;
        }

        $io->success([
            sprintf('Switched %d asset file(s) of licence %d to single use', $switched, $licenceId),
            sprintf('The search index is not updated: run anzu-dam:elastic:rebuild asset --ext-system=%s --no-drop', $licence->getExtSystem()->getSlug()),
            sprintf('The ext system is not notified: create a synchronize image changed job of licence %d', $licenceId),
        ]);

        return Command::SUCCESS;
    }
}
