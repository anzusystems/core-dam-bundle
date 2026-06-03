<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\FileSystem\Adapter\LocalFileSystemAdapter;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the {@see TtsAudioCreationInput} factory methods. Identity is purely content-addressed
 * (licence + sourceTextHash + voiceFamily); these assert source-text / metadata mapping only.
 */
final class TtsAudioCreationInputTest extends TestCase
{
    public function testForInitialRequestHashesLiveSourceText(): void
    {
        $request = (new TtsNarrationRequest())
            ->setTitle('My title')
            ->setSourceText('Hello world');

        $input = TtsAudioCreationInput::forInitialRequest(
            request: $request,
            audioFile: self::makeAdapterFile(),
            family: $this->makeFamily(),
            voice: $this->createMock(Voice::class),
            licence: $this->createMock(AssetLicence::class),
            sourceText: 'Hello world',
        );

        self::assertSame('Hello world', $input->sourceTextSnapshot);
        self::assertSame(hash('sha256', 'Hello world'), $input->sourceTextHash);
        self::assertSame('My title', $input->title);
    }

    public function testForRegenerateCarriesStableTtsSourceText(): void
    {
        $request = (new TtsNarrationRequest())
            ->setTitle('Regen title');

        $stableTts = $this->makeTtsAssetWithSource('stable-hash', 'stable snapshot text');

        $input = TtsAudioCreationInput::forRegenerate(
            request: $request,
            stableTts: $stableTts,
            audioFile: self::makeAdapterFile(),
            family: $this->makeFamily(),
            voice: $this->createMock(Voice::class),
            licence: $this->createMock(AssetLicence::class),
        );

        // Regeneration re-uses the original source text snapshot/hash, NOT the (empty) request source.
        self::assertSame('stable-hash', $input->sourceTextHash);
        self::assertSame('stable snapshot text', $input->sourceTextSnapshot);
        self::assertSame('Regen title', $input->title);
    }

    private static function makeAdapterFile(): AdapterFile
    {
        $tmpPath = (string) tempnam(sys_get_temp_dir(), 'tts_test_');
        file_put_contents($tmpPath, 'dummy');
        $dir = dirname($tmpPath);
        $adapter = new LocalFileSystemAdapter($dir);
        $fs = new LocalFilesystem($adapter, $dir);

        return new AdapterFile(path: $tmpPath, adapterPath: basename($tmpPath), filesystem: $fs);
    }

    private function makeFamily(): VoiceFamily
    {
        /** @var VoiceFamily $family */
        $family = (new ReflectionClass(VoiceFamily::class))->newInstanceWithoutConstructor();

        return $family;
    }

    private function makeTtsAssetWithSource(string $hash, string $snapshot): TtsAsset
    {
        /** @var TtsAsset $ttsAsset */
        $ttsAsset = (new ReflectionClass(TtsAsset::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass($ttsAsset);

        $hashProp = $ref->getProperty('sourceTextHash');
        $hashProp->setValue($ttsAsset, $hash);
        $snapProp = $ref->getProperty('sourceTextSnapshot');
        $snapProp->setValue($ttsAsset, $snapshot);

        return $ttsAsset;
    }
}
