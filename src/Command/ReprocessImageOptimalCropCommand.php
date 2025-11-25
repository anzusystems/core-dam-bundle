<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Exception;
use Generator;
use SplFileObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu:image:reprocess-optimal-crop',
    description: 'Create JobImageCopy based on licenceId and assetLicenceIds from file'
)]
final class ReprocessImageOptimalCropCommand extends Command
{
    use OutputUtilTrait;
    private const string IMAGE_FILE_OPT = 'image';

    private const string IMAGE_ID_FILE_PATH = 'file';

    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly ImageFacade $imageFacade,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addOption(
                name: self::IMAGE_FILE_OPT,
                mode: InputOption::VALUE_REQUIRED,
            )
            ->addOption(
                name: self::IMAGE_ID_FILE_PATH,
                mode: InputOption::VALUE_REQUIRED,
            )
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progressBar = new ProgressBar($output);
        $progressBar->start();
        foreach ($this->getImages($input) as $image) {
            $this->imageFacade->reprocessOptimalCrop($image);
        }

        $progressBar->finish();
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @return Generator<ImageFile>
     */
    private function getImages(InputInterface $input): Generator
    {
        $imageId = $input->getOption(self::IMAGE_FILE_OPT);

        if (is_string($imageId)) {
            $image = $this->imageFileRepository->find($imageId);

            if ($image instanceof ImageFile) {
                yield $image;
            }
        }

        $filePath = $input->getOption(self::IMAGE_ID_FILE_PATH);
        if (null === $filePath || false === file_exists($filePath)) {
            return;
        }

        $csv = new SplFileObject($filePath);
        $csv->setFlags(SplFileObject::SKIP_EMPTY);

        while (false === $csv->eof()) {
            $row = $csv->fgetcsv();

            if (false === is_array($row) || false === isset($row[0])) {
                continue;
            }

            $image = $this->imageFileRepository->find($imageId);

            if ($image instanceof ImageFile) {
                yield $image;
            }
        }
    }
}
