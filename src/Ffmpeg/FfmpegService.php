<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Ffmpeg;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use FFMpeg\Media\Frame;
use FFMpeg\Media\Video as FFMpegVideo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;
use Throwable;

final class FfmpegService
{
    public const string FRAME_EXTENSION = 'jpeg';
    public const string AUDIO_EXTENSION_MP3 = 'mp3';
    private const float MEASURE_TIMEOUT_SECONDS = 300.0;

    public function __construct(
        private readonly Exiftool $exiftool,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FfmpegException
     */
    public function populateAudioParams(AudioFile $audio, File $file): AudioFile
    {
        $filePath = $file->getRealPath();

        try {
            $ffProbe = FFProbe::create();
            $stream = $this->getFistAudioTrack($filePath);
        } catch (RuntimeException $exception) {
            throw new FfmpegException($exception->getMessage(), $exception->getPrevious());
        }

        if (null === $stream) {
            throw new FfmpegException(FfmpegException::ERROR_READ_STREAM);
        }

        $format = $ffProbe->format($filePath);

        $audio->getAttributes()
            ->setCodecName($stream->get('codec_name'))
            ->setBitrate((int) $format->get('bit_rate'))
            ->setDuration((int) $format->get('duration'))
        ;

        return $audio;
    }

    /**
     * @throws FfmpegException
     */
    public function populateVideoParams(VideoFile $video, File $file): VideoFile
    {
        $filePath = $file->getRealPath();

        try {
            $ffProbe = FFProbe::create();
            $stream = $this->getFistVideoTrack($filePath);
        } catch (RuntimeException $exception) {
            throw new FfmpegException($exception->getMessage(), $exception);
        }

        if (null === $stream) {
            throw new FfmpegException(FfmpegException::ERROR_READ_STREAM);
        }

        $dimensions = $stream->getDimensions();
        $format = $ffProbe->format($filePath);
        $gcd = Math::getGreatestCommonDivisor($dimensions->getWidth(), $dimensions->getHeight());

        $video->getAttributes()
            ->setRatioWidth((int) ($dimensions->getWidth() / $gcd))
            ->setRatioHeight((int) ($dimensions->getHeight() / $gcd))
            ->setBitrate((int) $format->get('bit_rate'))
            ->setWidth($dimensions->getWidth())
            ->setHeight($dimensions->getHeight())
            ->setDuration((int) $format->get('duration'))
            ->setCodecName($stream->get('codec_name'))
            ->setRotation($this->exiftool->getVideoRotation($filePath))
        ;

        return $video;
    }

    /**
     * @throws FfmpegException
     */
    public function getFileThumbnail(File $file, int $position): AdapterFile
    {
        $tmpFileSystem = $this->fileSystemProvider->getTmpFileSystem();

        try {
            $path = $tmpFileSystem->getTmpFileName(self::FRAME_EXTENSION);
            $this->getFrame($file, $position)->save($tmpFileSystem->extendPath($path));

            return AdapterFile::createFromBaseFile(
                file: new File($tmpFileSystem->extendPath($path)),
                filesystem: $tmpFileSystem
            );
        } catch (FfmpegException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FfmpegException(previous: $exception);
        }
    }

    /**
     * @throws FfmpegException
     */
    public function clipAudio(File $source, int $startSeconds, int $durationSeconds): AdapterFile
    {
        return $this->runToTmpMp3([
            '-y',
            '-ss', (string) $startSeconds,
            '-i', $source->getRealPath(),
            '-t', (string) $durationSeconds,
            '-c', 'copy',
        ]);
    }

    /**
     * Concat MP3 chunks via concat demuxer (stream-copy, same codec/rate/layout required).
     *
     * @param list<File> $parts
     *
     * @throws FfmpegException
     */
    public function concatAudio(array $parts): AdapterFile
    {
        if ([] === $parts) {
            throw new FfmpegException('Cannot concat empty parts list.');
        }

        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();

        // Escape only `\` and `'` as required by the concat demuxer format.
        $listLines = array_map(
            static fn (File $part): string => "file '" . str_replace(['\\', "'"], ['\\\\', "'\\''"], $part->getRealPath()) . "'",
            $parts,
        );
        $listAbsPath = $tmpFs->extendPath($tmpFs->writeTmpFileFromBytes(implode("\n", $listLines), 'txt'));

        return $this->runToTmpMp3([
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listAbsPath,
            '-c', 'copy',
        ]);
    }

    /**
     * Two-pass loudnorm (linear=true): the one-pass dynamic mode audibly warbles speech.
     * `-ar 44100` is mandatory: loudnorm works internally at 192 kHz, which libmp3lame cannot encode.
     *
     * @throws FfmpegException
     */
    public function normalizeLoudness(File $source, float $targetLufs, float $targetTruePeak, float $targetLra, int $bitrateKbps): AdapterFile
    {
        $measured = $this->measureLoudness($source, $targetLufs, $targetTruePeak, $targetLra);

        return $this->runToTmpMp3([
            '-y',
            '-i', $source->getRealPath(),
            '-af', sprintf(
                // %.6f: plain %s would render tiny floats in scientific notation and break the filter syntax.
                'loudnorm=I=%s:TP=%s:LRA=%s:measured_I=%.6f:measured_TP=%.6f:measured_LRA=%.6f:measured_thresh=%.6f:offset=%.6f:linear=true',
                $targetLufs,
                $targetTruePeak,
                $targetLra,
                $measured['input_i'],
                $measured['input_tp'],
                $measured['input_lra'],
                $measured['input_thresh'],
                $measured['target_offset'],
            ),
            '-c:a', 'libmp3lame',
            '-b:a', $bitrateKbps . 'k',
            '-ar', '44100',
        ]);
    }

    public function getFistVideoTrack(string $filePath): ?Stream
    {
        return FFProbe::create()
            ->streams($filePath)
            ->videos()
            ->first();
    }

    /**
     * @return array{input_i: float, input_tp: float, input_lra: float, input_thresh: float, target_offset: float}
     *
     * @throws FfmpegException
     */
    private function measureLoudness(File $source, float $targetLufs, float $targetTruePeak, float $targetLra): array
    {
        // Raw Process: the ffmpeg driver's command() returns stdout only, the loudnorm report is on stderr.
        $process = new Process([
            FFMpegDriver::create()->getProcessBuilderFactory()->getBinary(),
            '-hide_banner', '-nostats',
            '-i', $source->getRealPath(),
            '-af', sprintf('loudnorm=I=%s:TP=%s:LRA=%s:print_format=json', $targetLufs, $targetTruePeak, $targetLra),
            '-f', 'null', '-',
        ]);
        $process->setTimeout(self::MEASURE_TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new FfmpegException($exception->getMessage(), $exception);
        }

        // The loudnorm report is a flat JSON block on stderr, followed by ffmpeg's closing summary lines.
        $stderr = $process->getErrorOutput();
        $jsonStart = strrpos($stderr, '{');
        $jsonEnd = false === $jsonStart ? false : strpos($stderr, '}', $jsonStart);
        if (false === $process->isSuccessful() || false === $jsonStart || false === $jsonEnd) {
            throw new FfmpegException('Loudness measurement pass failed: ' . trim(substr($stderr, -200)));
        }

        $report = json_decode(substr($stderr, $jsonStart, $jsonEnd - $jsonStart + 1), true);
        $measured = [];
        foreach (['input_i', 'input_tp', 'input_lra', 'input_thresh', 'target_offset'] as $key) {
            if (false === is_numeric($report[$key] ?? null)) {
                throw new FfmpegException(sprintf('Loudness measurement returned non-numeric "%s".', $key));
            }
            $measured[$key] = (float) $report[$key];
        }

        return $measured;
    }

    /**
     * @param list<string> $command
     *
     * @throws FfmpegException
     */
    private function runToTmpMp3(array $command): AdapterFile
    {
        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();
        $outAbsPath = $tmpFs->extendPath($tmpFs->getTmpFileName(self::AUDIO_EXTENSION_MP3));
        $command[] = $outAbsPath;

        try {
            FFMpeg::create()->getFFMpegDriver()->command($command);
        } catch (Throwable $exception) {
            throw new FfmpegException($exception->getMessage(), $exception);
        }

        return AdapterFile::createFromBaseFile(
            file: new File($outAbsPath),
            filesystem: $tmpFs,
        );
    }

    private function getFistAudioTrack(string $filePath): ?Stream
    {
        return FFProbe::create()
            ->streams($filePath)
            ->audios()
            ->first();
    }

    /**
     * @psalm-suppress UndefinedMethod
     */
    private function getFrame(File $file, int $position): Frame
    {
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($file->getRealPath());

        if (false === ($video instanceof FFMpegVideo)) {
            throw new FfmpegException(FfmpegException::ERROR_UNSUPPORTED_MEDIA_TYPE);
        }

        return $video->frame(
            TimeCode::fromSeconds($position)
        );
    }
}
