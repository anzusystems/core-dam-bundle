<?php

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use Symfony\Contracts\Service\Attribute\Required;

trait AdminImageLinksTrait
{
    use ImageLinksTrait;

    /**
     * @return CropAllowItem[]
     */
    protected function getTaggedList(ImageFile $imageFile, string $tag): array
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset(
            $imageFile->getAsset()
        );

        return $this->allowListConfiguration->getTaggedList($config->getAdminDomainName(), $tag);
    }

    protected function getDomain(ImageFile $imageFile): string
    {
        $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration($imageFile->getExtSystem()->getSlug());

        return $config->getAdminDomain();
    }
}