<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use Doctrine\ORM\NonUniqueResultException;

final class AssetMetadataManager extends AbstractManager
{
    public function __construct(
        private readonly CustomFormProvider $customFormProvider,
    ) {
    }

    public function create(AssetMetadata $assetMetadata, bool $flush = true): AssetMetadata
    {
        $this->trackCreation($assetMetadata);
        $this->entityManager->persist($assetMetadata);
        $this->flush($flush);

        return $assetMetadata;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function updateFromCustomData(
        Asset $asset,
        array $customData,
        bool $flush = true
    ): AssetMetadata {
        $assetMetadata = $asset->getMetadata();
        $this->trackModification($assetMetadata);
        $assetMetadata->setCustomData($this->updateCustomData($asset, $customData));
        $this->flush($flush);

        return $assetMetadata;
    }

    public function removeSuggestions(AssetMetadata $assetMetadata, bool $flush = true): AssetMetadata
    {
        $this->trackModification($assetMetadata);
        $assetMetadata
            ->setAuthorSuggestions([])
            ->setKeywordSuggestions([])
        ;
        $this->flush($flush);

        return $assetMetadata;
    }

    /**
     * @throws NonUniqueResultException
     */
    private function updateCustomData(Asset $asset, array $newCustomData): array
    {
        $form = $this->customFormProvider->provideForm($asset);

        $oldCustomData = $asset->getMetadata()->getCustomData();

        foreach ($form->getElements() as $element) {
            if ($element->getAttributes()->isReadonly()) {
                continue;
            }

            if (false === array_key_exists($element->getProperty(), $newCustomData)) {
                unset($oldCustomData[$element->getProperty()]);

                continue;
            }

            $oldCustomData[$element->getProperty()] = $newCustomData[$element->getProperty()];
        }

        return $oldCustomData;
    }
}
