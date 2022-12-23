<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Symfony\Contracts\Service\Attribute\Required;

trait EntityValidatorAwareTrait
{
    protected EntityValidator $entityValidator;

    #[Required]
    public function setEntityValidator(EntityValidator $entityValidator): void
    {
        $this->entityValidator = $entityValidator;
    }
}
