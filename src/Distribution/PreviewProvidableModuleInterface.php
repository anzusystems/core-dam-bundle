<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;

interface PreviewProvidableModuleInterface
{
    public function getPreviewLink(Distribution $distribution): ?string;
}
