<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormManager;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\Embeds\CustomFormElementAttributes;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<AssetCustomForm>
 */
final class CustomFormElementFixtures extends AbstractFixtures
{
    public function __construct(
        private readonly CustomFormManager $customFormManager,
        private readonly CustomFormFactory $customFormFactory,
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetCustomForm::class;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function load(ProgressBar $progressBar): void
    {
        /** @var CustomForm $customForm */
        foreach ($progressBar->iterate($this->getData()) as $newCustomForm) {
            $customForm = $this->assetCustomFormRepository->findOneByTypeAndExtSystem(
                $newCustomForm->getExtSystem(),
                $newCustomForm->getAssetType()
            );
            if ($customForm) {
                $this->customFormManager->update($customForm, $newCustomForm);

                continue;
            }

            $this->customFormManager->create($newCustomForm);
        }
    }

    /**
     * @return Generator<int, CustomForm>
     */
    private function getData(): Generator
    {
        $extSystem = $this->entityManager->getPartialReference(ExtSystem::class, 1);

        yield $this->createImageCustomForm($extSystem);
        yield $this->createAudioCustomForm($extSystem);
        yield $this->createVideoCustomForm($extSystem);
        yield $this->createDocumentCustomForm($extSystem);
    }

    private function createImageCustomForm(ExtSystem $extSystem): AssetCustomForm
    {
        return $this->customFormFactory->createAssetCustomForm(
            AssetType::Image,
            $extSystem
        )
            ->setElements(
                new ArrayCollection([
                    (new CustomFormElement())
                        ->setKey('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setKey('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(2_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setKey('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setKey('rightsUsageTerms')
                        ->setName('Rights Usage Terms')
                        ->setExifAutocomplete(['RightsUsageTerms', 'Rights'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('personInImage')
                        ->setName('PersonInImage')
                        ->setExifAutocomplete(['PersonInImage'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::StringArray)
                                ->setMaxValue(128)
                                ->setMaxCount(32)
                        ),
                ]),
            );
    }

    private function createAudioCustomForm(ExtSystem $extSystem): AssetCustomForm
    {
        return $this->customFormFactory->createAssetCustomForm(
            AssetType::Audio,
            $extSystem
        )
            ->setElements(
                new ArrayCollection([
                    (new CustomFormElement())
                        ->setKey('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setKey('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setKey('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setKey('rightsUsageTerms')
                        ->setName('Rights Usage Terms')
                        ->setExifAutocomplete(['RightsUsageTerms', 'Rights'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                ])
            );
    }

    private function createDocumentCustomForm(ExtSystem $extSystem): AssetCustomForm
    {
        return $this->customFormFactory->createAssetCustomForm(
            AssetType::Document,
            $extSystem
        )
            ->setElements(
                new ArrayCollection([
                    (new CustomFormElement())
                        ->setKey('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setKey('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(2_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setKey('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setKey('rightsUsageTerms')
                        ->setName('Rights Usage Terms')
                        ->setExifAutocomplete(['RightsUsageTerms', 'Rights'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                ])
            );
    }

    private function createVideoCustomForm(ExtSystem $extSystem): AssetCustomForm
    {
        return $this->customFormFactory->createAssetCustomForm(
            AssetType::Video,
            $extSystem
        )
            ->setElements(
                new ArrayCollection([
                    (new CustomFormElement())
                        ->setKey('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setKey('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setKey('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setKey('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setKey('rightsUsageTerms')
                        ->setName('Rights Usage Terms')
                        ->setExifAutocomplete(['RightsUsageTerms', 'Rights'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                ])
            );
    }
}
