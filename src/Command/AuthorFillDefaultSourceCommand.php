<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorDefaultSourceFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:fill-default-source',
    description: 'Give the default source of the ext system to its images with a file and without a source'
)]
final readonly class AuthorFillDefaultSourceCommand
{
    public function __construct(
        private ExtSystemRepository $extSystemRepository,
        private AuthorProvider $authorProvider,
        private AssetSourceDBALRepository $assetSourceDBALRepository,
        private AuthorDefaultSourceFacade $authorDefaultSourceFacade,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Slug of the ext system whose images get the default source')]
        string $extSystem = App::EMPTY_STRING,
        #[Option(description: 'Write nothing, only show the default source and count the images without a source')]
        bool $dryRun = false,
    ): int {
        $extSystemEntity = $this->extSystemRepository->findOneBySlug($extSystem);
        if (null === $extSystemEntity) {
            $io->error(sprintf('Ext system "%s" not found, --ext-system=<slug> is required', $extSystem));

            return Command::INVALID;
        }

        $source = $this->authorProvider->getDefaultSource($extSystemEntity, AssetType::Image);
        if (null === $source) {
            $io->error(sprintf('Images of ext system %s have no default source', $extSystem));

            return Command::FAILURE;
        }
        $io->definitionList(
            ['Default source' => sprintf('%s (%s)', $source->getName(), (string) $source->getId())],
            ['Hidden in credit' => $source->getFlags()->isHiddenInCredit() ? 'yes' : 'no'],
        );

        $counts = $this->assetSourceDBALRepository->countImagesWithoutSource((int) $extSystemEntity->getId());
        $io->table(['Status', 'Images without a source'], array_map(null, array_keys($counts), $counts));
        if ($dryRun) {
            $io->success('Dry run, nothing written. Only images with a file get the default source');

            return Command::SUCCESS;
        }

        $progressBar = $io->createProgressBar($counts[AssetStatus::WithFile->value] ?? App::ZERO);
        $progressBar->setFormat('debug');
        foreach ($this->authorDefaultSourceFacade->fillDefaultSource($extSystemEntity, $source) as $batchCount) {
            $progressBar->advance($batchCount);
        }
        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('Filled %d images', $progressBar->getProgress()));

        return Command::SUCCESS;
    }
}
