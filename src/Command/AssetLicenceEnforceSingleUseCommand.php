<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseEnforcer;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:asset-licence:enforce-single-use',
    description: 'Switch every asset file of a licence with flags.singleUseEnforced to single use and reindex it'
)]
final readonly class AssetLicenceEnforceSingleUseCommand
{
    public function __construct(
        private AssetLicenceRepository $assetLicenceRepository,
        private AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Asset licence to enforce single use on', name: 'licence_id')]
        int $licenceId,
    ): int {
        $licence = $this->assetLicenceRepository->find($licenceId);
        if (null === $licence) {
            $io->error('Asset licence not found');

            return Command::FAILURE;
        }
        if (false === $licence->getFlags()->isSingleUseEnforced()) {
            $io->error('Asset licence does not enforce single use, nothing to do');

            return Command::FAILURE;
        }

        $enforcedCount = $this->assetFileSingleUseEnforcer->enforceLicence($licence);
        $io->success(sprintf('Switched %d asset file(s) of licence %d to single use', $enforcedCount, $licence->getId()));

        return Command::SUCCESS;
    }
}
