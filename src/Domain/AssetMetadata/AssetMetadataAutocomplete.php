<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Parser\ElementParserInterface;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class AssetMetadataAutocomplete
{
    private readonly iterable $parsers;

    public function __construct(
        public readonly CustomFormProvider $customFormProvider,
        #[AutowireIterator(tag: ElementParserInterface::class, indexAttribute: 'key')]
        iterable $parsers,
    ) {
        $this->parsers = $parsers;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function autocompleteMetadata(AssetFile $assetFile): AssetFile
    {
        $asset = $assetFile->getAsset();

        $form = $this->customFormProvider->provideFormByAssetProvidable($asset);
        $assetCustomData = [];

        foreach ($form->getElements() as $element) {
            $autocompletedValue = $this->autocompleteElement($element, $assetFile->getMetadata()->getExifData());
            if ($autocompletedValue) {
                $assetCustomData[$element->getProperty()] = $autocompletedValue;
            }
        }

        $asset->getMetadata()->setCustomData(array_merge(
            $asset->getMetadata()->getCustomData(),
            $assetCustomData
        ));
        $asset->getAssetFlags()->setAutocompletedMetadata(true);

        return $assetFile;
    }

    private function autocompleteElement(CustomFormElement $customFormElement, array $metadata): mixed
    {
        if (empty($customFormElement->getExifAutocomplete())) {
            return null;
        }

        $parser = $this->getParser($customFormElement);
        foreach ($customFormElement->getExifAutocomplete() as $autocompleteKey) {
            if (isset($metadata[$autocompleteKey]) && false === empty($metadata[$autocompleteKey])) {
                return $parser->parse($customFormElement, $metadata[$autocompleteKey]);
            }
        }

        return null;
    }

    /**
     * @throws DomainException
     */
    private function getParser(CustomFormElement $customFormElement): ElementParserInterface
    {
        foreach ($this->parsers as $key => $parser) {
            if ($customFormElement->getAttributes()->getType()->toString() === $key) {
                return $parser;
            }
        }

        throw new DomainException(sprintf(
            'Parser for type (%s) is missing',
            $customFormElement->getAttributes()->getType()->toString()
        ));
    }
}
