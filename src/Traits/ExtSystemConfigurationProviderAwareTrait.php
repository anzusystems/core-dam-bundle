<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use Symfony\Contracts\Service\Attribute\Required;

trait ExtSystemConfigurationProviderAwareTrait
{
    protected ExtSystemConfigurationProvider $extSystemConfigurationProvider;

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $extSystemConfigurationProvider): void
    {
        $this->extSystemConfigurationProvider = $extSystemConfigurationProvider;
    }
}
