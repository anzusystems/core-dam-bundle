<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Process\Process;

final class Exiftool
{
    private const array PNG_CLEAR = ['-png:all=', '-overwrite_original'];
    private const array READ_TAGS = ['-json', '-charset', 'utf8'];
    private const float DEFAULT_TIMEOUT = 15.0;

    private readonly ExifTagNormalizer $tagNormalizer;

    public function __construct(
        private readonly string $exiftoolBin,
        private readonly DamLogger $damLogger,
        private readonly ?string $iptcFallbackCharset = null,
        ?ExifTagNormalizer $tagNormalizer = null,
    ) {
        $this->tagNormalizer = $tagNormalizer ?? new ExifTagNormalizer($iptcFallbackCharset);
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

        // Byte-passthrough read for undeclared IPTC so normalizeIptcCharset() can detect the real
        // per-value charset; a declared CodedCharacterSet still takes priority over this override.
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
        return $this->tagNormalizer->normalizeTags(
            $this->decodeTags($output) ?? throw new RuntimeException('Exiftool returned unparsable output'),
        );
    }

    private function decodeTags(string $output): ?array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) && is_array($decoded[0] ?? null)
            ? $decoded[0]
            : null;
    }
}
