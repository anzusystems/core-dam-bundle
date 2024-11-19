<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu:image:reprocess-optimal-crop',
    description: 'Create JobImageCopy based on licenceId and assetLicenceIds from file'
)]
final class ReprocessImageOptimalCropCommand extends Command
{
    use OutputUtilTrait;
    private const string IMAGE_FILE_ARG = 'image';
    private const int MAX_ASSETS_PER_JOB = 20;

    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly ImageFacade $imageFacade,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addArgument(
                name: self::IMAGE_FILE_ARG,
                mode: InputArgument::REQUIRED,
            )
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $imageId = $input->getArgument(self::IMAGE_FILE_ARG);
        $image = $this->imageFileRepository->find($imageId);

        if (null === $image) {
            $output->writeln("<error>Image not found: ({$imageId})</error>");

            return Command::FAILURE;
        }

        $this->imageFacade->reprocessOptimalCrop($image);

        return Command::SUCCESS;
    }
}
