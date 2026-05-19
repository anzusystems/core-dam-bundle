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
        if (false === $sentences) {
            return [$text];
        }

        // PREG_SPLIT_NO_EMPTY + non-empty input guarantees at least one sentence — seed with it.
        $chunks = [];
        $currentChunk = array_shift($sentences);

        foreach ($sentences as $sentence) {
            $candidate = $currentChunk . ' ' . $sentence;
            if (mb_strlen($candidate) > $maxCharsPerChunk) {
                $chunks[] = $currentChunk;
                $currentChunk = $sentence;
            } else {
                $currentChunk = $candidate;
            }
        }

        $chunks[] = $currentChunk;

        return $chunks;
    }
}
