<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetSlot;

use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit test for {@see AssetSlotFactory::replaceSlotFile()} — the repoint used by TTS regeneration to swing a
 * slot onto a freshly built file while leaving the previous file attached to the asset (so its public CDN URL
 * survives the grace period). The factory is built without its constructor: the repoint path touches neither
 * the slot manager nor the configuration provider.
 */
final class AssetSlotFactoryReplaceTest extends TestCase
{
    public function testReplaceSlotFileRepointsSlotAndReturnsPrevious(): void
    {
        /** @var AssetSlotFactory $factory */
        $factory = (new ReflectionClass(AssetSlotFactory::class))->newInstanceWithoutConstructor();

        $asset = new Asset();
        $previous = new AudioFile();
        $replacement = new AudioFile();

        $slot = (new AssetSlot())->setName('master');
        $slot->getFlags()->setMain(true);
        $slot->setAsset($asset);
        $previous->addSlot($slot);
        $asset->addSlot($slot);

        $returned = $factory->replaceSlotFile($asset, $replacement, 'master');

        self::assertSame($previous, $returned, 'the previously slotted file must be returned for grace handling');
        self::assertSame($replacement, $slot->getAudio(), 'the slot must now point at the replacement');
        self::assertSame($replacement, $asset->getMainFile(), 'the main slot must repoint the asset main file');
        self::assertSame($asset, $replacement->getAsset(), 'the replacement keeps the required asset FK');
        self::assertTrue($replacement->getSlots()->contains($slot));
        self::assertFalse($previous->getSlots()->contains($slot), 'the previous file is detached from the slot but kept on the asset');
    }
}
