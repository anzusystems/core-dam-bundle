<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Domain\Asset;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsProcessor;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Configuration\DisplayTitleConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;

final class DisplayTitleProcessorTest extends CoreDamKernelTestCase
{
    protected AssetTextsProcessor $displayTitleProcessor;
    protected ConfigurationProvider $configurationProvider;
    protected AssetTextsWriter $assetTextsWriter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->displayTitleProcessor = $this->getService(AssetTextsProcessor::class);
        $this->configurationProvider = $this->getService(ConfigurationProvider::class);
        $this->assetTextsWriter = $this->getService(AssetTextsWriter::class);
    }

    /**
     * @dataProvider displayTitleFullDataProvider
     */
    public function testDisplayTitleFull(array $config, string $expectedTitle): void
    {
        $asset = (new Asset())
            ->setId('asset-id')
            ->setMetadata(
                (new AssetMetadata())->setCustomData([
                    'title' => 'Custom data title',
                    'headline' => 'Custom data headline',
                ])
            );

        $image = (new ImageFile())
            ->setId('image-id')
            ->setAssetAttributes(
                (new AssetFileAttributes())
                    ->setOriginFileName('filename.jpg')
            );
        $asset->setMainFile($image);

        $assetSlot = (new AssetSlot())
            ->setAsset($asset)
            ->setImage($image);
        $assetSlot
            ->getFlags()
            ->setDefault(true);
        $asset->setSlots(new ArrayCollection([$assetSlot]));

        $this->configurationProvider->setDisplayTitleConfiguration(
            (new DisplayTitleConfiguration($config, [], [], []))
        );

        $this->assertSame(
            $expectedTitle,
            $this->displayTitleProcessor->getAssetDisplayTitle($asset),
        );
    }

    public function displayTitleFullDataProvider(): array
    {
        return [
            [
                'config' => [
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'metadata.customData[title]']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'metadata.customData[headline]']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile.assetAttributes.originFileName']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'id']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile.id']),
                ],
                'expectedTitle' => 'Custom data title'
            ],
            [
                'config' => [
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile.assetAttributes.originFileName']),
                ],
                'expectedTitle' => 'filename.jpg'
            ],
            [
                'config' => [
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'metadata.customData[description]']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile.id']),
                ],
                'expectedTitle' => 'image-id'
            ]
        ];
    }

    /**
     * @dataProvider displayTitleEmptyDataProvider
     */
    public function testDisplayTitleEmpty(array $config): void
    {
        $asset = (new Asset())
            ->setMetadata((new AssetMetadata()));

        $this->configurationProvider->setDisplayTitleConfiguration(
            (new DisplayTitleConfiguration($config, [], [], []))
        );

        $this->assertSame(
            '',
            $this->displayTitleProcessor->getAssetDisplayTitle($asset),
        );
    }

    public function displayTitleEmptyDataProvider(): array
    {
        return [
            [
                'config' => [
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'metadata.customData[title]']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'metadata.customData[headline]']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile?.assetAttributes.originFileName']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'id']),
                    TextsWriterConfiguration::getFromArrayConfiguration(['source_property_path' => 'mainFile?.id']),
                ],
            ],
        ];
    }
}
