<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use Symfony\Contracts\Service\Attribute\Required;

trait FileHelperTrait
{
    protected MimeGuesser $fileHelper;

    #[Required]
    public function setFileHelper(MimeGuesser $fileHelper): void
    {
        $this->fileHelper = $fileHelper;
    }
}
