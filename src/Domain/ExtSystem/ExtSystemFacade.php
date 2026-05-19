<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Dto\ExtSystem\ExtSystemTtsSettingsUpdateDto;

final class ExtSystemFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly ExtSystemManager $extSystemManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(ExtSystem $extSystem, ExtSystem $newExtSystem): ExtSystem
    {
        $this->validator->validate($newExtSystem, $extSystem);

        return $this->extSystemManager->update($extSystem, $newExtSystem);
    }

    /**
     * @throws ValidationException
     */
    public function updateTtsSettings(ExtSystem $extSystem, ExtSystemTtsSettingsUpdateDto $dto): ExtSystem
    {
        $this->validator->validate($dto);

        return $this->extSystemManager->updateTtsSettings($extSystem, $dto);
    }
}
