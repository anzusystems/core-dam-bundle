<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileMetadataProcessMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final class ChunkFacade
{
    public function __construct(
        private readonly ChunkManager $chunkManager,
        private readonly EntityValidator $entityValidator,
        private readonly ChunkFactory $chunkFactory,
        private readonly ChunkFileManager $chunkFileManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(ChunkAdmCreateDto $createDto, AssetFile $assetFile): Chunk
    {
        $createDto->setAssetFile($assetFile);
        $this->entityValidator->validateDto($createDto);
        $chunk = $this->chunkFactory->createFromAdmDto($createDto);
        $this->chunkManager->setAssetFile($chunk, $assetFile);
        $this->chunkManager->setNotifyTo($assetFile);
        $uploadedFile = $createDto->getFile();

        try {
            $this->chunkManager->beginTransaction();
            $this->chunkFileManager->saveChunk($chunk, $uploadedFile);
            $assetFile->getAssetAttributes()->setUploadedSize(
                $assetFile->getAssetAttributes()->getUploadedSize() + $createDto->getSize()
            );
            $this->chunkManager->create($chunk);

            if ($chunk->isFirstChunk()) {
                $this->messageBus->dispatch(new AssetFileMetadataProcessMessage($assetFile, $chunk));
            }

            $this->chunkManager->commit();

            return $chunk;
        } catch (Throwable $exception) {
            $this->chunkManager->rollback();

            throw new RuntimeException('asset_create_failed', 0, $exception);
        }
    }
}
