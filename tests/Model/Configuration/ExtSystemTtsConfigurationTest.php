<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Model\Configuration;

use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemTtsConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExtSystemTtsConfigurationTest extends TestCase
{
    #[DataProvider('provideOutputFormatResolution')]
    public function testOutputFormatResolution(mixed $configured, string $expected): void
    {
        $config = ExtSystemTtsConfiguration::getFromArrayConfiguration(
            null === $configured ? [] : [ExtSystemTtsConfiguration::OUTPUT_FORMAT_KEY => $configured],
        );

        self::assertSame($expected, $config->outputFormat);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideOutputFormatResolution(): iterable
    {
        yield 'absent → default' => [null, ExtSystemTtsConfiguration::DEFAULT_OUTPUT_FORMAT];
        yield 'empty (unset env via default::) → default' => ['', ExtSystemTtsConfiguration::DEFAULT_OUTPUT_FORMAT];
        yield 'zero is not empty' => ['0', '0'];
        yield '128k explicit' => ['mp3_44100_128', 'mp3_44100_128'];
        yield '192k explicit' => ['mp3_44100_192', 'mp3_44100_192'];
    }

    #[DataProvider('provideBitrate')]
    public function testGetOutputBitrateKbps(string $outputFormat, int $expected): void
    {
        self::assertSame($expected, self::config($outputFormat)->getOutputBitrateKbps());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideBitrate(): iterable
    {
        yield '128k → 128' => ['mp3_44100_128', 128];
        yield '192k → 192' => ['mp3_44100_192', 192];
    }

    public function testBitrateLookupRejectsUnsupportedOutputFormat(): void
    {
        $config = self::config('pcm_44100');
        self::assertSame('pcm_44100', $config->outputFormat);

        $this->expectException(TtsProviderException::class);

        $config->getOutputBitrateKbps();
    }

    private static function config(string $outputFormat): ExtSystemTtsConfiguration
    {
        return ExtSystemTtsConfiguration::getFromArrayConfiguration([
            ExtSystemTtsConfiguration::OUTPUT_FORMAT_KEY => $outputFormat,
        ]);
    }
}
