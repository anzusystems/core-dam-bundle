<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\App;

final readonly class TextChunker
{
    /**
     * Splits at sentence boundaries; over-long sentences are hard-split by words then chars.
     *
     * @return list<string>
     */
    public function chunk(string $text, int $maxCharsPerChunk): array
    {
        if (App::EMPTY_STRING === $text) {
            return [];
        }

        if (mb_strlen($text) <= $maxCharsPerChunk) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, flags: PREG_SPLIT_NO_EMPTY);
        if (false === $sentences) {
            $sentences = [$text];
        }

        $chunks = [];
        $currentChunk = App::EMPTY_STRING;

        foreach ($sentences as $sentence) {
            // Sentence exceeds cap — flush current, then hard-split.
            if (mb_strlen($sentence) > $maxCharsPerChunk) {
                if (App::EMPTY_STRING !== $currentChunk) {
                    $chunks[] = $currentChunk;
                    $currentChunk = App::EMPTY_STRING;
                }
                foreach ($this->splitLongSentence($sentence, $maxCharsPerChunk) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            $candidate = App::EMPTY_STRING === $currentChunk ? $sentence : $currentChunk . ' ' . $sentence;
            if (mb_strlen($candidate) <= $maxCharsPerChunk) {
                $currentChunk = $candidate;

                continue;
            }
            $chunks[] = $currentChunk;
            $currentChunk = $sentence;
        }

        if (App::EMPTY_STRING !== $currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function splitLongSentence(string $sentence, int $max): array
    {
        $pieces = [];
        $current = App::EMPTY_STRING;

        foreach (explode(' ', $sentence) as $word) {
            if (mb_strlen($word) > $max) {
                if (App::EMPTY_STRING !== $current) {
                    $pieces[] = $current;
                    $current = App::EMPTY_STRING;
                }
                foreach ($this->splitByChars($word, $max) as $part) {
                    $pieces[] = $part;
                }

                continue;
            }

            $candidate = App::EMPTY_STRING === $current ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $max) {
                $current = $candidate;

                continue;
            }
            $pieces[] = $current;
            $current = $word;
        }

        if (App::EMPTY_STRING !== $current) {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * @return list<string>
     */
    private function splitByChars(string $text, int $max): array
    {
        $parts = [];
        $length = mb_strlen($text);
        for ($offset = App::ZERO; $offset < $length; $offset += $max) {
            $parts[] = mb_substr($text, $offset, $max);
        }

        return $parts;
    }
}
