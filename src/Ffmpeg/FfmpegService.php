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
use FFMpeg\Exception\RuntimeException;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use FFMpeg\Media\Frame;
use FFMpeg\Media\Video as FFMpegVideo;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

final class FfmpegService
{
    public const string FRAME_EXTENSION = 'jpeg';
    public const string AUDIO_EXTENSION_MP3 = 'mp3';

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
     * `-ar 44100` is mandatory: loudnorm works internally at 192 kHz, which libmp3lame cannot encode.
     *
     * @throws FfmpegException
     */
    public function normalizeLoudness(File $source, float $targetLufs, float $targetTruePeak, float $targetLra, int $bitrateKbps): AdapterFile
    {
        return $this->runToTmpMp3([
            '-y',
            '-i', $source->getRealPath(),
            '-af', sprintf('loudnorm=I=%s:TP=%s:LRA=%s', $targetLufs, $targetTruePeak, $targetLra),
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
