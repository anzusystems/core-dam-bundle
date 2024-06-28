<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Ffmpeg;

use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use Throwable;

final class FfmpegMimeDetector
{
    private const string CODEC_TAG_STRING = 'codec_tag_string';
    private const string CODEC_NAME = 'codec_name';

    private const array AUDIO_FORMAT_MIME_TYPES = [
        'mp4a' => 'audio/m4a',
    ];

    private const AUDIO_CODEC_NAME_MAP = [
        'mp3' => 'audio/mpeg',
    ];

    private const VIDEO_STREAM_IMAGE_CODEC_NAMES = ['png'];

    public function detectAudioMime(string $path): ?string
    {
        try {
            $streams = FFProbe::create()->streams($path);
            $audioStream = $streams->audios()->first();

            // check if video has no videoStream or all video streams are static images
            foreach ($streams->videos() as $videoStream) {
                $codecName = $videoStream->get(self::CODEC_NAME);

                if (false === (is_string($codecName) && in_array($codecName, self::VIDEO_STREAM_IMAGE_CODEC_NAMES, true))) {
                    return null;
                }
            }

            if ($audioStream instanceof Stream) {
                return $this->detectAudioStreamMime($audioStream);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function detectAudioStreamMime(Stream $stream): ?string
    {
        $tag = $stream->get(self::CODEC_TAG_STRING);
        if (is_string($tag) && isset(self::AUDIO_FORMAT_MIME_TYPES[$tag])) {
            return self::AUDIO_FORMAT_MIME_TYPES[$tag];
        }

        $codecName = $stream->get(self::CODEC_NAME);
        if (is_string($codecName) && isset(self::AUDIO_CODEC_NAME_MAP[$codecName])) {
            return self::AUDIO_CODEC_NAME_MAP[$codecName];
        }

        return null;
    }
}
