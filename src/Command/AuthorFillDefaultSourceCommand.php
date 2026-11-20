<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\JobImageDefaultSource;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:fill-default-source',
    description: 'Give the default source of the ext system to its images with a file and without a source, in a job'
)]
final readonly class AuthorFillDefaultSourceCommand
{
    public function __construct(
        private ExtSystemRepository $extSystemRepository,
        private AuthorProvider $authorProvider,
        private AssetSourceDBALRepository $assetSourceDBALRepository,
        private JobFacade $jobFacade,
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

        if ($dryRun) {
            $counts = $this->assetSourceDBALRepository->countImagesWithoutSource((int) $extSystemEntity->getId());
            $io->table(['Status', 'Images without a source'], array_map(null, array_keys($counts), $counts));
            $io->success('Dry run, nothing written. Only images with a file get the default source');

            return Command::SUCCESS;
        }

        $job = $this->jobFacade->create((new JobImageDefaultSource())->setExtSystemId((int) $extSystemEntity->getId()));
        $io->success(sprintf('Job %d created', (int) $job->getId()));

        return Command::SUCCESS;
    }
}
