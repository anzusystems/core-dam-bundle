<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Ffmpeg;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Helper\Math;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use Symfony\Component\HttpFoundation\File\File;

final class FfmpegService
{
    public function __construct(
        private readonly Exiftool $exiftool,
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

    public function getFistVideoTrack(string $filePath): ?Stream
    {
        return FFProbe::create()
            ->streams($filePath)
            ->videos()
            ->first();
    }

    private function getFistAudioTrack(string $filePath): ?Stream
    {
        return FFProbe::create()
            ->streams($filePath)
            ->audios()
            ->first();
    }
}
