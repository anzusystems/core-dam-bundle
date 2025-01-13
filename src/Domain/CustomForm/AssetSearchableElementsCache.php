<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use AnzuSystems\CoreDamBundle\Repository\CustomFormElementRepository;

final class AssetSearchableElementsCache
{
    /**
     * @var array<int, array<string, string[]>>
     */
    private array $customFormElementsCache = [];

    public function __construct(
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
        private readonly CustomFormElementRepository $customFormElementRepository,
    ) {
    }

    public function getSearchableCustomFormProperties(int $extSystemId, string $assetType): array
    {
        if (false === array_key_exists($extSystemId, $this->customFormElementsCache)) {
            $this->warmupExtSystemCache($extSystemId);
        }

        return $this->customFormElementsCache[$extSystemId][$assetType] ?? [];
    }

    private function warmupExtSystemCache(int $extSystemId): void
    {
        $forms = $this->assetCustomFormRepository->findAllByExtSystem($extSystemId);
        $this->customFormElementsCache[$extSystemId] = [];

        foreach ($forms as $form) {
            $this->customFormElementsCache[$extSystemId][$form->getAssetType()->value] = [];
            $formElements = $this->customFormElementRepository->findAllAssetSearchableElementsByForms([$form->getId()]);

            foreach ($formElements as $element) {
                $this->customFormElementsCache[$extSystemId][$form->getAssetType()->value][] = $element->getProperty();
            }
        }
    }
}
