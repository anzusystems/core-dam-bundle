<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Podcast>
 */
final class PodcastFixtures extends AbstractAssetFileFixtures
{
    public const PODCAST_1 = '8fe7196e-1480-41b6-b0b3-1c73c79f3452';
    public const PODCAST_2 = '5edeb44d-c64b-4357-957d-688d9cf7e63a';

    public function __construct(
        private readonly PodcastManager $podcastManager,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            ImageFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return Podcast::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Podcast $podcast */
        foreach ($progressBar->iterate($this->getData()) as $podcast) {
            $podcast = $this->podcastManager->create($podcast);
            $this->addToRegistry($podcast, $podcast->getId());
        }
    }

    private function getData(): Generator
    {
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $podcast = (new Podcast())
            ->setId(self::PODCAST_1)
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('This American Life')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://www.thisamericanlife.org/podcast/rss.xml')
            )
            ->setLicence($licence);

        $podcast->setImagePreview(
            $this->imagePreviewFactory->createFromImageFile(
                imageFile: $this->imageFileRepository->find(ImageFixtures::IMAGE_ID_1_2),
                flush: false
            )
        );

        yield $podcast;
    }
}
