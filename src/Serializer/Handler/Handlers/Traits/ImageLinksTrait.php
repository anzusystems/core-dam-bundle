<?php

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use Symfony\Contracts\Service\Attribute\Required;

trait ImageLinksTrait
{
    protected ExtSystemConfigurationProvider $extSystemConfigurationProvider;
    protected AllowListConfiguration $allowListConfiguration;

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $extSystemConfigurationProvider): void
    {
        $this->extSystemConfigurationProvider = $extSystemConfigurationProvider;
    }

    #[Required]
    public function setAllowListConfiguration(AllowListConfiguration $allowListConfiguration): void
    {
        $this->allowListConfiguration = $allowListConfiguration;
    }
}