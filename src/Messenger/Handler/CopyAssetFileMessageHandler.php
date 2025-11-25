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
use Throwable;

#[AsMessageHandler]
final class CopyAssetFileMessageHandler
{
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly ImageCopyFacade $imageCopyFacade,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     * @throws Throwable
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
