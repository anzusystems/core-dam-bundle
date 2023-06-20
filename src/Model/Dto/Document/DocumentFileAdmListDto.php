<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Document;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class DocumentFileAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = DocumentFile::class;
    protected DocumentFile $document;

    #[Serialize]
    protected AssetFileAttributesAdmDto $fileAttributes;

    public static function getInstance(DocumentFile $documentFile): static
    {
        /** @psalm-var DocumentFileAdmListDto $parent */
        $parent = parent::getBaseInstance($documentFile);

        return $parent
            ->setFileAttributes(AssetFileAttributesAdmDto::getInstance($documentFile->getAssetAttributes()))
            ->setDocument($documentFile);
    }

    public function getFileAttributes(): AssetFileAttributesAdmDto
    {
        return $this->fileAttributes;
    }

    public function setFileAttributes(AssetFileAttributesAdmDto $fileAttributes): self
    {
        $this->fileAttributes = $fileAttributes;

        return $this;
    }

    public function getDocument(): DocumentFile
    {
        return $this->document;
    }

    public function setDocument(DocumentFile $document): self
    {
        $this->document = $document;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getAsset(): Asset
    {
        return $this->document->getAsset();
    }
}
