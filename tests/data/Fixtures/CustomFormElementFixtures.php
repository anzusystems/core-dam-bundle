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
        $extSystem = $this->entityManager->getPartialReference(ExtSystem::class, 4);

        yield $this->createImageCustomForm($extSystem);
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
                        ->setKey('description')
                        ->setName('Description')
                        ->setExifAutocomplete(['Description', 'ImageDescription'])
                        ->setAttributes(
                            (new CustomFormElementAttributes())
                                ->setType(CustomFormElementType::String)
                                ->setMaxValue(2_000)
                        ),
                    (new CustomFormElement())
                        ->setKey('author')
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
}
