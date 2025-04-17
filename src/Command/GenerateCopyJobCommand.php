<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\FilesystemException;
use SplFileObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'anzu:job:copy-asset-files',
    description: 'Create JobImageCopy based on licenceId and assetLicenceIds from file'
)]
final class GenerateCopyJobCommand extends Command
{
    use OutputUtilTrait;
    private const string IMAGE_FILE_PATH_OPT = 'file';
    private const string LICENCE_ID_ARG = 'licence';
    private const string IMAGE_IDS_CSV = 'image_ids.csv';

    private const int MAX_ASSETS_PER_JOB = 1_000;

    public function __construct(
        private readonly Connection $damMediaApiMigConnection,
        private readonly Connection $defaultConnection,
        private readonly JobImageCopyFacade $imageCopyFacade,
        private readonly AssetRepository $assetRepository,
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addArgument(
                name: self::LICENCE_ID_ARG,
                mode: InputArgument::REQUIRED,
            )
            ->addOption(
                name: self::IMAGE_FILE_PATH_OPT,
                mode: InputOption::VALUE_REQUIRED,
                default: AnzuApp::getDataDir() . '/' . self::IMAGE_IDS_CSV
            );
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $licenceId = $input->getArgument(self::LICENCE_ID_ARG);
        $licence = $this->assetLicenceRepository->find($licenceId);

        if (null === $licence) {
            $output->writeln("<error>Licence not found: ({$licenceId})</error>");

            return Command::FAILURE;
        }
        $filePath = $input->getOption(self::IMAGE_FILE_PATH_OPT);
        if (false === file_exists($filePath)) {
            $output->writeln("<error>File not found at path: ({$filePath})</error>");

            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf('Copy to licence (%s)? [y/n] ', $licence->getName()), false);

        if (false === $helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $csv = new SplFileObject($filePath);
        $csv->setFlags(SplFileObject::SKIP_EMPTY);
        $progress = new ProgressBar($output);
        $progress->start();

        /** @var array<array-key, Asset> $assets */
        $assets = [];
        while (false === $csv->eof()) {
            $row = $csv->fgetcsv();

            if (false === is_array($row) || false === isset($row[0])) {
                continue;
            }

            $assetFile = $this->assetFileRepository->find($row[0]);
            if (false === ($assetFile instanceof AssetFile)) {
                continue;
            }

            $assets[(string) $assetFile->getAsset()->getId()] = $assetFile->getAsset();
            if (count($assets) >= self::MAX_ASSETS_PER_JOB) {
                $this->imageCopyFacade->createPodcastSynchronizerJob($licence, new ArrayCollection($assets));
                $assets = [];
                $this->entityManager->clear();
                /** @var AssetLicence $licence */
                $licence = $this->assetLicenceRepository->find($licenceId);
            }

            $progress->advance();
        }

        if (false === empty($assets)) {
            $this->imageCopyFacade->createPodcastSynchronizerJob($licence, new ArrayCollection($assets));
        }

        $progress->finish();
        $output->writeln('');

        return Command::SUCCESS;
    }
}
