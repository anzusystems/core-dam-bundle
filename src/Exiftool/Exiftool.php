<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Process\Process;

final class Exiftool
{
    private const array PNG_CLEAR = ['-png:all=', '-overwrite_original'];
    private const array READ_TAGS = ['-json', '-charset', 'utf8'];
    private const float DEFAULT_TIMEOUT = 15.0;

    // Exiftool's "latin1" IPTC charset is actually its "Latin" table (cp1252, not literal ISO-8859-1,
    // see exiftool's Charset/Latin.pm: "cp1252 to Unicode ... table omits 1-byte characters with the
    // same values as Unicode"): most bytes 0x80-0x9F pass through as U+0080-U+009F, but these 27 map to
    // smart-quote/Š/Ž-style punctuation above U+00FF instead. Reversing that restores true 1:1 byte
    // passthrough, which normalizeIptcCharset() below depends on.
    private const array LATIN_CHARSET_OVERRIDE_UNDO = [
        "\u{20AC}" => "\u{0080}", "\u{201A}" => "\u{0082}", "\u{0192}" => "\u{0083}",
        "\u{201E}" => "\u{0084}", "\u{2026}" => "\u{0085}", "\u{2020}" => "\u{0086}",
        "\u{2021}" => "\u{0087}", "\u{02C6}" => "\u{0088}", "\u{2030}" => "\u{0089}",
        "\u{0160}" => "\u{008A}", "\u{2039}" => "\u{008B}", "\u{0152}" => "\u{008C}",
        "\u{017D}" => "\u{008E}", "\u{2018}" => "\u{0091}", "\u{2019}" => "\u{0092}",
        "\u{201C}" => "\u{0093}", "\u{201D}" => "\u{0094}", "\u{2022}" => "\u{0095}",
        "\u{2013}" => "\u{0096}", "\u{2014}" => "\u{0097}", "\u{02DC}" => "\u{0098}",
        "\u{2122}" => "\u{0099}", "\u{0161}" => "\u{009A}", "\u{203A}" => "\u{009B}",
        "\u{0153}" => "\u{009C}", "\u{017E}" => "\u{009E}", "\u{0178}" => "\u{009F}",
    ];

    public function __construct(
        private readonly string $exiftoolBin,
        private readonly DamLogger $damLogger,
        private readonly ?string $iptcFallbackCharset = null,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function getTags(string $filePath): array
    {
        try {
            return $this->parseOutput($this->execute($filePath, $this->buildReadTags()));
        } catch (RuntimeException $exception) {
            $this->damLogger->error(DamLogger::NAMESPACE_EXIFTOOL, $exception->getMessage(), exception: $exception);

            return [];
        }
    }

    public function clearPng(string $filePath): void
    {
        try {
            $this->execute($filePath, self::PNG_CLEAR);
        } catch (RuntimeException $exception) {
            $this->damLogger->error(DamLogger::NAMESPACE_EXIFTOOL, $exception->getMessage(), exception: $exception);
        }
    }

    /**
     * @throws SerializerException
     */
    public function getVideoRotation(string $filePath): int
    {
        $tags = $this->getTags($filePath);

        return isset($tags['Rotation']) ? (int) $tags['Rotation'] : 0;
    }

    /**
     * @return string[]
     */
    private function buildReadTags(): array
    {
        if (null === $this->iptcFallbackCharset || App::EMPTY_STRING === $this->iptcFallbackCharset) {
            return self::READ_TAGS;
        }

        // IPTC:CodedCharacterSet is missing on most archival photos; reading with a fixed byte-passthrough
        // charset (also exiftool's own default assumption for undeclared IPTC) lets getTags() detect the
        // real per-value charset afterward instead of guessing one charset for the whole file. Files that
        // DO declare a CodedCharacterSet (e.g. UTF8) still take priority over this override, unaffected.
        return [...self::READ_TAGS, '-charset', 'iptc=latin1'];
    }

    private function execute(string $filePath, array $command = []): string
    {
        $commandParts = [$this->exiftoolBin, $filePath];
        $commandParts = array_merge($commandParts, $command);
        $process = new Process($commandParts);

        $process->setTimeout(self::DEFAULT_TIMEOUT);
        $process->run();

        if (false === $process->isSuccessful()) {
            throw new RuntimeException(
                empty($process->getErrorOutput())
                    ? $this->getErrorFromOutput($process->getOutput())
                    : $process->getErrorOutput()
            );
        }

        return $process->getOutput();
    }

    // Error tag arrives as JSON on read paths, colon format on write paths run without -json.
    private function getErrorFromOutput(string $output): string
    {
        $error = $this->decodeTags($output)['Error'] ?? null;
        if (is_string($error)) {
            return $error;
        }

        foreach (explode(PHP_EOL, $output) as $line) {
            $pair = explode(':', $line, 2);
            if ('Error' === trim($pair[0]) && isset($pair[1])) {
                return trim($pair[1]);
            }
        }

        return '';
    }

    /**
     * @throws RuntimeException when the output is not parsable, so a failed extract is not reported as "no tags"
     */
    private function parseOutput(string $output): array
    {
        $decoded = $this->decodeTags($output)
            ?? throw new RuntimeException('Exiftool returned unparsable output');

        $tagList = [];
        foreach ($decoded as $tagName => $tagValue) {
            if (is_scalar($tagValue)) {
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(
                    trim($this->normalizeIptcCharset((string) $tagValue))
                );

                continue;
            }
            // List tags (Keywords, Subject) come back as JSON arrays; keep the flat form consumers already expect.
            if (is_array($tagValue)) {
                $values = array_map(
                    fn (mixed $item): string => $this->normalizeIptcCharset((string) $item),
                    array_filter($tagValue, 'is_scalar'),
                );
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(implode(', ', $values));
            }
        }

        return $tagList;
    }

    // Pure ASCII and text already above U+00FF never went through the latin1 byte-passthrough read
    // (buildReadTags()) — it's declared UTF-8/XMP/EXIF — so both are returned untouched. A value confined
    // to U+0080-U+00FF is passthrough bytes reinterpreted as Latin-1, recoverable losslessly; if those
    // bytes are themselves valid UTF-8, the source file was undeclared UTF-8, otherwise fall back to the
    // configured legacy charset — non-trivial cp1250 text virtually never forms valid UTF-8 by chance, so
    // the validity check is a deterministic discriminator.
    private function normalizeIptcCharset(string $value): string
    {
        if (null === $this->iptcFallbackCharset || App::EMPTY_STRING === $this->iptcFallbackCharset) {
            return $value;
        }

        if (mb_check_encoding($value, 'ASCII')) {
            return $value;
        }

        $passthrough = strtr($value, self::LATIN_CHARSET_OVERRIDE_UNDO);
        if (1 === preg_match('/[^\x{00}-\x{FF}]/u', $passthrough)) {
            return $value;
        }

        $bytes = mb_convert_encoding($passthrough, 'ISO-8859-1', 'UTF-8');
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        $recovered = iconv($this->iptcFallbackCharset, 'UTF-8', $bytes);

        return false === $recovered ? $value : $recovered;
    }

    private function decodeTags(string $output): ?array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) && is_array($decoded[0] ?? null)
            ? $decoded[0]
            : null;
    }
}
