<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Ffmpeg;

use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;
use Throwable;

final class FfmpegMimeDetector
{
    private const CODEC_TAG_STRING = 'codec_tag_string';

    private const AUDIO_FORMAT_MIME_TYPES = [
        'mp4a' => 'audio/m4a',
    ];

    public function detectMime(string $path): ?string
    {
        try {
            $streams = FFProbe::create()->streams($path);
            $videoStream = $streams->videos()->first();
            $audioStream = $streams->audios()->first();

            if (null === $videoStream && $audioStream instanceof Stream) {
                return $this->detectAudioMime($audioStream);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function detectAudioMime(Stream $stream): ?string
    {
        $tag = $stream->get(self::CODEC_TAG_STRING);
        if (is_string($tag) && isset(self::AUDIO_FORMAT_MIME_TYPES[$tag])) {
            return self::AUDIO_FORMAT_MIME_TYPES[$tag];
        }

        return null;
    }
}
