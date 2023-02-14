<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileCounter;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileMetadataProcessMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final class ChunkFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly ChunkManager $chunkManager,
        private readonly ChunkFactory $chunkFactory,
        private readonly ChunkFileManager $chunkFileManager,
        private readonly MessageBusInterface $messageBus,
        private readonly AssetFileCounter $assetFileCounter,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function create(ChunkAdmCreateDto $createDto, AssetFile $assetFile): Chunk
    {
        $createDto->setAssetFile($assetFile);
        $this->validator->validate($createDto);
        $chunk = $this->chunkFactory->createFromAdmDto($createDto);
        $this->chunkManager->setAssetFile($chunk, $assetFile);
        $this->chunkManager->setNotifyTo($assetFile);
        $uploadedFile = $createDto->getFile();
        if (null === $uploadedFile) {
            throw new RuntimeException('Uploaded file must be set at this step');
        }

        $uploadedSize = (int) $uploadedFile->getSize();

        try {
            $this->chunkManager->beginTransaction();
            $this->chunkFileManager->saveChunk($chunk, $uploadedFile);

            $this->assetFileCounter->incrUploadedSize($assetFile, $uploadedSize);
            $this->chunkManager->create($chunk);
            $this->chunkManager->commit();

            if ($chunk->isFirstChunk()) {
                $this->messageBus->dispatch(new AssetFileMetadataProcessMessage($assetFile, $chunk));
            }

            return $chunk;
        } catch (Throwable $exception) {
            $this->assetFileCounter->resetUploadedSize($assetFile);
            $this->chunkManager->rollback();

            throw new RuntimeException('asset_create_failed', 0, $exception);
        }
    }
}
