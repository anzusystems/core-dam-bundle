<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Domain\Asset;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsProcessor;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Configuration\DisplayTitleConfiguration;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;

final class DisplayTitleProcessorTest extends AnzuKernelTestCase
{
    protected AssetTextsProcessor $displayTitleProcessor;
    protected ConfigurationProvider $configurationProvider;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var AssetTextsProcessor $displayTitleProcessor */
        $displayTitleProcessor = self::getContainer()->get(AssetTextsProcessor::class);
        $this->displayTitleProcessor = $displayTitleProcessor;

        /** @var ConfigurationProvider $configurationProvider */
        $configurationProvider = self::getContainer()->get(ConfigurationProvider::class);
        $this->configurationProvider = $configurationProvider;
    }

    /**
     * @dataProvider getDisplayTitleProvider
     *
     * @throws NonUniqueResultException
     */
    public function testGetDisplayTitle(array $imageDisplayTitleConfiguration, string $expectedTitle): void
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

        $assetSlot = (new AssetSlot())
            ->setAsset($asset)
            ->setImage($image);
        $assetSlot
            ->getFlags()
            ->setDefault(true);
        $asset->setSlots(new ArrayCollection([$assetSlot]));

        $this->configurationProvider->setDisplayTitleConfiguration(
            (new DisplayTitleConfiguration($imageDisplayTitleConfiguration, [], [], []))
        );

        $this->assertEquals(
            $expectedTitle,
            $this->displayTitleProcessor->getAssetDisplayTitle($asset),
        );
    }

    public function getDisplayTitleProvider(): array
    {
        return [
            [
                ['customData:title'],
                'Custom data title',
            ],
            [
                ['customData:table'],
                '',
            ],
            [
                ['asset:id'],
                'asset-id',
            ],
        ];
    }
}
