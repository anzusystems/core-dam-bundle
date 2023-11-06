<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Document;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class DocumentFileAdmListDto extends AbstractAssetFileAdmDto
{
    protected string $resourceName = DocumentFile::class;

    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    protected DocumentFile $document;

    public static function getInstance(DocumentFile $documentFile): static
    {
        /** @psalm-var DocumentFileAdmListDto $parent */
        $parent = parent::getAssetFileBaseInstance($documentFile);

        return $parent
            ->setDocument($documentFile);
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
