<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;

trait AdminImageLinksTrait
{
    use ImageLinksTrait;

    /**
     * @return CropAllowItem[]
     */
    protected function getTaggedList(ImageFile $imageFile, string $tag): array
    {
        $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration(
            $imageFile->getAsset()->getExtSystem()->getSlug()
        );

        return $this->allowListConfiguration->getTaggedList($config->getAdminDomainName(), $tag);
    }

    protected function getDomain(ImageFile $imageFile): string
    {
        $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration(
            $imageFile->getExtSystem()->getSlug()
        );

        return $config->getAdminDomain();
    }
}
