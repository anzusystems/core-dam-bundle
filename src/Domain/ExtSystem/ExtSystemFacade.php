<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

final class ExtSystemFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly ExtSystemManager $extSystemManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(ExtSystem $extSystem, ExtSystem $newExtSystem): ExtSystem
    {
        $this->entityValidator->validate($newExtSystem, $extSystem);

        return $this->extSystemManager->update($extSystem, $newExtSystem);
    }
}
