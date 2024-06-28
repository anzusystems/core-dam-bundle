<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\VideoShow\VideoShowManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowTexts;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<VideoShow>
 */
final class VideoShowFixtures extends AbstractAssetFileFixtures
{
    public const string SHOW_1 = '5edeb44d-c64b-4357-957d-688d9cf7e63a';

    public function __construct(
        private readonly VideoShowManager $videoShowManager,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            VideoFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return VideoShow::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var VideoShow $videoShow */
        foreach ($progressBar->iterate($this->getData()) as $videoShow) {
            $videoShow = $this->videoShowManager->create($videoShow);
            $this->addToRegistry($videoShow, $videoShow->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        yield (new VideoShow())
            ->setId(self::SHOW_1)
            ->setTexts(
                (new VideoShowTexts())
                    ->setTitle('Rozhovory ZKH')
            )
            ->setLicence($licence);
    }
}
