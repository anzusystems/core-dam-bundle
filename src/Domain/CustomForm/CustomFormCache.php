<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;

final class CustomFormCache
{
    /**
     * @var array<string, CustomForm>
     */
    private array $forms = [];

    public function getFromCache(ResourceCustomFormProvidableInterface|AssetCustomFormProvidableInterface $formProvidable): ?CustomForm
    {
        $key = $this->getKey($formProvidable);
        if (isset($this->forms[$key])) {
            return $this->forms[$key];
        }

        return null;
    }

    public function saveToCache(
        ResourceCustomFormProvidableInterface|AssetCustomFormProvidableInterface $formProvidable,
        CustomForm $form
    ): CustomForm {
        $this->forms[$this->getKey($formProvidable)] = $form;

        return $form;
    }

    private function getKey(
        ResourceCustomFormProvidableInterface|AssetCustomFormProvidableInterface $formProvidable,
    ): string {
        if ($formProvidable instanceof ResourceCustomFormProvidableInterface) {
            return $formProvidable->getResourceKey();
        }

        return $formProvidable->getExtSystem()->getSlug() . '_' . $formProvidable->getAssetType()->toString();
    }
}
