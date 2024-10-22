<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Messenger\Message\CopyAssetFileMessage;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CopyAssetFileMessageHandler
{
    use IndexManagerAwareTrait;

    /**
     * @param class-string $userEntityClass
     */
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly string $userEntityClass,
        private readonly EntityManagerInterface $entityManager,
        //        private readonly AssetRepository $assetRepository,
        //        private readonly AssetPropertiesRefresher $refresher,
        //        private readonly AssetManager $manager,
        //        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    public function __invoke(CopyAssetFileMessage $message): void
    {
        $asset = $this->assetRepository->find($message->getAssetId());
        if (null === $asset) {
            return;
        }
        $copyAsset = $this->assetRepository->find($message->getCopyAssetId());
        if (null === $copyAsset) {
            return;
        }

        $this->imageCopyFacade->copyAssetFiles($asset, $copyAsset);
    }
}
