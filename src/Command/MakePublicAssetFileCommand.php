<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFileRouteAdmCreateDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'anzu-dam:asset-file-route:make-public',
    description: 'Create the main public route for an asset file of any type without HTTP timeout limits'
)]
final class MakePublicAssetFileCommand extends Command
{
    use ValidatorAwareTrait;

    private const string ARG_ASSET_FILE_ID = 'asset_file_id';
    private const string OPT_SLUG = 'slug';
    private const string OPT_FORCE = 'force';

    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly AssetFileRouteFacade $assetFileRouteFacade,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument(
            name: self::ARG_ASSET_FILE_ID,
            mode: InputArgument::REQUIRED,
        );
        $this->addOption(
            name: self::OPT_SLUG,
            mode: InputOption::VALUE_REQUIRED,
            description: 'Route slug; defaults to the asset display title',
            default: App::EMPTY_STRING,
        );
        $this->addOption(
            name: self::OPT_FORCE,
            mode: InputOption::VALUE_NONE,
            description: 'Replace an existing main route (runs make private first)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $assetFileId = (string) $input->getArgument(self::ARG_ASSET_FILE_ID);
        $dto = (new AssetFileRouteAdmCreateDto())->setSlug((string) $input->getOption(self::OPT_SLUG));

        // An empty slug is a valid input: the route factory then derives it from the asset title.
        if (App::EMPTY_STRING !== $dto->getSlug()) {
            $this->validator->validate($dto);
        }

        $assetFile = $this->assetFileRepository->find($assetFileId);
        if (null === $assetFile) {
            $output->writeln(sprintf('<error>AssetFile (%s) not found.</error>', $assetFileId));

            return Command::FAILURE;
        }

        $mainRouteRemoved = false;
        if (
            $input->getOption(self::OPT_FORCE)
            && $this->assetFileRouteRepository->findMainByAssetFile($assetFileId) instanceof AssetFileRoute
        ) {
            $this->assetFileRouteFacade->makePrivate($assetFile);
            $mainRouteRemoved = true;
            $output->writeln('Existing main route removed.');
        }

        try {
            $route = $this->assetFileRouteFacade->makePublicAssetFile(assetFile: $assetFile, dto: $dto);
        } catch (Throwable $exception) {
            if ($mainRouteRemoved) {
                $output->writeln(
                    '<error>The previous main route is already removed, the asset file is currently not public. Re-run without --force to finish.</error>'
                );
            }

            throw $exception;
        } finally {
            $this->fileSystemProvider->getTmpFileSystem()->tryClearPaths();
        }

        $output->writeln(sprintf('Public route created: %s', $route->getUri()->getPath()));

        return Command::SUCCESS;
    }
}
