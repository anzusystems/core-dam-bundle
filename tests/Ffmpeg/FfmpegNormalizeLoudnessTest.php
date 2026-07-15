<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Ffmpeg;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use FFMpeg\FFProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\File;

final class FfmpegNormalizeLoudnessTest extends CoreDamKernelTestCase
{
    private const string STEREO_SAMPLE = '/tests/data/Files/audioa';

    #[DataProvider('provideBitrate')]
    public function testNormalizeLoudnessProducesTargetMasterFormat(int $bitrateKbps): void
    {
        $ffmpeg = $this->getService(FfmpegService::class);
        $source = new File(App::getProjectDir() . self::STEREO_SAMPLE);

        $normalized = $ffmpeg->normalizeLoudness($source, -18.0, -1.5, 11.0, $bitrateKbps);
        $path = $normalized->getRealPath();
        self::assertNotFalse($path);

        $probe = FFProbe::create();
        $audio = $probe->streams($path)->audios()->first();
        self::assertNotNull($audio);
        self::assertSame('mp3', $audio->get('codec_name'));
        self::assertSame(44_100, (int) $audio->get('sample_rate'));
        self::assertSame(2, (int) $audio->get('channels'));

        $actualKbps = (int) round(((int) $probe->format($path)->get('bit_rate')) / 1_000);
        self::assertGreaterThanOrEqual($bitrateKbps - 24, $actualKbps);
        self::assertLessThanOrEqual($bitrateKbps + 24, $actualKbps);

        // Sample is ~-21.7 LUFS; single-pass loudnorm targeting -18 lands ~-19, a no-op would stay near -21.7.
        $lufs = self::measureIntegratedLufs($path);
        self::assertGreaterThanOrEqual(-20.5, $lufs, 'Loudness was not normalized toward the target.');
        self::assertLessThanOrEqual(-15.5, $lufs);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideBitrate(): iterable
    {
        yield '128k master' => [128];
        yield '192k master' => [192];
    }

    private static function measureIntegratedLufs(string $path): float
    {
        exec(
            sprintf('ffmpeg -hide_banner -nostats -i %s -af loudnorm=print_format=summary -f null - 2>&1', escapeshellarg($path)),
            $lines,
        );
        self::assertSame(
            1,
            preg_match('/Input Integrated:\s*(-?\d+(?:\.\d+)?)\s*LUFS/', implode("\n", $lines), $matches),
            'Could not read integrated LUFS from ffmpeg loudnorm output.',
        );

        return (float) $matches[1];
    }
}
