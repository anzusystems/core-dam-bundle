<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
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
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetCustomForm::class;
    }

    public static function getDependencies(): array
    {
        return [ExtSystemFixtures::class];
    }

    /**
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function load(ProgressBar $progressBar): void
    {
        /** @var CustomForm $customForm */
        foreach ($progressBar->iterate($this->getData()) as $newCustomForm) {
            $this->customFormManager->create($newCustomForm);
        }
    }

    /**
     * @return Generator<int, CustomForm>
     */
    private function getData(): Generator
    {
        $blogExtSystem = $this->entityManager->getPartialReference(ExtSystem::class, ExtSystemFixtures::ID_BLOG);
        $cmsExtSystem = $this->entityManager->getPartialReference(ExtSystem::class, ExtSystemFixtures::ID_CMS);

        yield $this->createBlogImageCustomForm($blogExtSystem);
        yield $this->createImageCustomForm($cmsExtSystem);
        yield $this->createAudioCustomForm($cmsExtSystem);
        yield $this->createVideoCustomForm($cmsExtSystem);
        yield $this->createDocumentCustomForm($cmsExtSystem);
    }

    private function createBlogImageCustomForm(ExtSystem $extSystem): AssetCustomForm
    {
        return $this->customFormFactory->createAssetCustomForm(
            AssetType::Image,
            $extSystem
        )
            ->setElements(
                new ArrayCollection([
                    (new CustomFormElement())
                        ->setProperty('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(2_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('author')
                        ->setName('Author')
                        ->setExifAutocomplete(['Author'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                ]),
            );
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
                           ->setProperty('headline')
                           ->setName('Headline')
                           ->setExifAutocomplete(['Headline', 'Subject'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setSearchable(true)
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(256)
                           ),
                       (new CustomFormElement())
                           ->setProperty('title')
                           ->setName('Title')
                           ->setExifAutocomplete(['Title', 'Subject'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setSearchable(true)
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(64)
                           ),
                       (new CustomFormElement())
                           ->setProperty('description')
                           ->setName('Description')
                           ->setExifAutocomplete(['Description', 'ImageDescription'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(2_000)
                           ),
                       (new CustomFormElement())
                           ->setProperty('creditLine')
                           ->setName('Credit Line')
                           ->setExifAutocomplete(['Credit'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(256)
                           ),
                       (new CustomFormElement())
                           ->setProperty('altText')
                           ->setName('Alt Text')
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(5_000)
                           ),
                       (new CustomFormElement())
                           ->setProperty('source')
                           ->setName('Source')
                           ->setExifAutocomplete(['Source'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(32)
                           ),
                       (new CustomFormElement())
                           ->setProperty('copyrightNotice')
                           ->setName('Copyright Notice')
                           ->setExifAutocomplete(['CopyrightNotice'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(128)
                           ),
                       (new CustomFormElement())
                           ->setProperty('rightsUsageTerms')
                           ->setName('Rights Usage Terms')
                           ->setExifAutocomplete(['RightsUsageTerms', 'Rights'])
                           ->setAttributes(
                               (new CustomFormElementAttributes())
                                   ->setType(CustomFormElementType::String)
                                   ->setMaxValue(256)
                           ),
                       (new CustomFormElement())
                           ->setProperty('personInImage')
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
                        ->setProperty('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setProperty('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setProperty('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setProperty('rightsUsageTerms')
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
                        ->setProperty('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setProperty('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(2_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setProperty('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setProperty('rightsUsageTerms')
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
                        ->setProperty('headline')
                        ->setName('Headline')
                        ->setExifAutocomplete(['Headline', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('title')
                        ->setName('Title')
                        ->setExifAutocomplete(['Title', 'Subject'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setSearchable(true)
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(64)
                        ),
                    (new CustomFormElement())
                        ->setProperty('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('creditLine')
                        ->setName('Credit Line')
                        ->setExifAutocomplete(['Credit'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(256)
                        ),
                    (new CustomFormElement())
                        ->setProperty('altText')
                        ->setName('Alt Text')
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(5_000)
                        ),
                    (new CustomFormElement())
                        ->setProperty('source')
                        ->setName('Source')
                        ->setExifAutocomplete(['Source'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(32)
                        ),
                    (new CustomFormElement())
                        ->setProperty('copyrightNotice')
                        ->setName('Copyright Notice')
                        ->setExifAutocomplete(['CopyrightNotice'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(128)
                        ),
                    (new CustomFormElement())
                        ->setProperty('rightsUsageTerms')
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
