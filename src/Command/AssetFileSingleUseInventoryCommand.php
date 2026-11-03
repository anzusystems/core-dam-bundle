<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseInventoryFacade;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileSingleUseInventoryRow;
use DateTimeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:asset-file:single-use-inventory',
    description: 'List single use groups the ext system reports with more than one holder; writes nothing',
)]
final readonly class AssetFileSingleUseInventoryCommand
{
    private const string HOLDER_SEPARATOR = ';';

    public function __construct(
        private AssetFileSingleUseInventoryFacade $assetFileSingleUseInventoryFacade,
    ) {
    }

    public function __invoke(SymfonyStyle $io, OutputInterface $output): int
    {
        $output->writeln('rootId,assetId,licenceId,recordedHolder,holders,firstUsedAt');

        $result = $this->assetFileSingleUseInventoryFacade->inventory(
            fn (AssetFileSingleUseInventoryRow $row) => $output->writeln($this->formatRow($row)),
        );

        $io->getErrorStyle()->writeln(sprintf(
            'Checked %d group(s): %d with 2+ holders, %d unanswered',
            $result->getChecked(),
            $result->getWithManyHolders(),
            $result->getUnanswered(),
        ));

        return Command::SUCCESS;
    }

    private function formatRow(AssetFileSingleUseInventoryRow $row): string
    {
        $firstUsedAt = $row->getFirstUsedAt();

        return implode(',', [
            $row->getRootId(),
            $row->getAssetId(),
            $row->getLicenceId(),
            $row->getRecordedHolder(),
            implode(self::HOLDER_SEPARATOR, $row->getHolders()),
            $firstUsedAt instanceof DateTimeInterface ? $firstUsedAt->format(DateTimeInterface::ATOM) : App::EMPTY_STRING,
        ]);
    }
}
