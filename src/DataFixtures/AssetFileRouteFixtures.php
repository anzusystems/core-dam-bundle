<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFileRouteAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<AssetFileRoute>
 */
final class AssetFileRouteFixtures extends AbstractAssetFileFixtures
{
    public function __construct(
        private readonly AssetFileRouteFacade $assetFileRouteFacade,
        private readonly AssetFileRouteManager $assetFileRouteManager,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            ImageFixtures::class,
            DocumentFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return AssetFileRoute::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var array{assetFile: AssetFile, dto: AssetFileRouteAdmCreateDto} $data */
        foreach ($progressBar->iterate($this->getData()) as $data) {
            $this->assetFileRouteFacade->makePublic($data['assetFile'], $data['dto']);
        }

        $this->createLegacy();
    }

    private function createLegacy(): void
    {
        $assetFile = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        if (null === $assetFile) {
            return;
        }

        $route = (new AssetFileRoute())
            ->setUri(
                (new RouteUri())
                    ->setSlug('')
                    ->setMain(false)
                    ->setPath('artemis/usmedata/files/9/01/19/pani-jolana.jpg')
            )
            ->setMode(RouteMode::Direct)
            ->setStatus(RouteStatus::Active)
        ;
        $route->setTargetAssetFile($assetFile);
        $assetFile->getRoutes()->add($route);

        $this->assetFileRouteManager->create($route);
    }

    private function getData(): Generator
    {
        yield [
            'assetFile' => $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1),
            'dto' => (new AssetFileRouteAdmCreateDto())
                ->setSlug('image'),
        ];

        yield [
            'assetFile' => $this->entityManager->find(DocumentFile::class, DocumentFixtures::DOC_ID_3),
            'dto' => (new AssetFileRouteAdmCreateDto())
                ->setSlug('document'),
        ];
    }
}
