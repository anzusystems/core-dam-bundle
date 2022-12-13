<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class JwMediaMetadataDetailDto extends JwMediaMetadataDto
{
    #[Serialize]
    private string $status = '';

    public function getStatus(): string
    {
        return $this->status;
    }
}
