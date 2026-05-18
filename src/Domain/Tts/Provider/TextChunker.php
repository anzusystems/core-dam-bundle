<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

final readonly class TextChunker
{
    /**
     * Splits at sentence boundaries — a single sentence over $maxCharsPerChunk is emitted as its own
     * oversized chunk rather than mid-sentence broken.
     *
     * @return list<string>
     */
    public function chunk(string $text, int $maxCharsPerChunk): array
    {
        if ('' === $text) {
            return [];
        }

        if (mb_strlen($text) <= $maxCharsPerChunk) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, flags: PREG_SPLIT_NO_EMPTY);
        if (false === $sentences || 0 === count($sentences)) {
            return [$text];
        }

        $chunks = [];
        $currentChunk = null;

        foreach ($sentences as $sentence) {
            if (null === $currentChunk) {
                $currentChunk = $sentence;

                continue;
            }

            $candidate = $currentChunk . ' ' . $sentence;
            if (mb_strlen($candidate) > $maxCharsPerChunk) {
                $chunks[] = $currentChunk;
                $currentChunk = $sentence;
            } else {
                $currentChunk = $candidate;
            }
        }

        if (null !== $currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }
}
