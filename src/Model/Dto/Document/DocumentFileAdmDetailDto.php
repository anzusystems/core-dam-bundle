<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Document;

use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DocumentFileAdmDetailDto extends DocumentFileAdmListDto
{
    public static function getInstance(DocumentFile $documentFile): static
    {
        return parent::getInstance($documentFile);
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->document->getAssetAttributes()->getOriginAssetId();
    }
}
