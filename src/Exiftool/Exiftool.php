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

    // Byte-passthrough read for undeclared IPTC so ExifTagNormalizer can detect the real per-value
    // charset; a declared CodedCharacterSet still takes priority over this override.
    private const array IPTC_PASSTHROUGH = ['-charset', 'iptc=latin1'];
    private const string CODED_CHARACTER_SET = 'CodedCharacterSet';
    private const float DEFAULT_TIMEOUT = 15.0;

    public function __construct(
        private readonly string $exiftoolBin,
        private readonly DamLogger $damLogger,
        private readonly ExifTagNormalizer $tagNormalizer,
        private readonly ?string $iptcFallbackCharset = null,
    ) {
    }

    /**
     * @return array<string, string> tag name => flattened value
     *
     * @throws SerializerException
     */
    public function getTags(string $filePath): array
    {
        try {
            $decoded = $this->decodeTags($this->execute($filePath, $this->buildReadTags()))
                ?? throw new RuntimeException('Exiftool returned unparsable output');

            return $this->tagNormalizer->normalizeTags($decoded, $this->resolveRecoverableTagNames($filePath, $decoded));
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

    private function isCharsetRecoveryEnabled(): bool
    {
        return false === (null === $this->iptcFallbackCharset || App::EMPTY_STRING === $this->iptcFallbackCharset);
    }

    /**
     * @return string[]
     */
    private function buildReadTags(): array
    {
        return $this->isCharsetRecoveryEnabled()
            ? [...self::READ_TAGS, ...self::IPTC_PASSTHROUGH]
            : self::READ_TAGS;
    }

    /**
     * Names of the tags charset recovery may touch — an empty list disables it for this file.
     *
     * A file that declares its IPTC charset was already decoded correctly by exiftool (the
     * declaration outranks the passthrough override), so recovering it would corrupt values in the
     * Latin-1 range wherever cp1250 disagrees with it (£ would become Ł). The declaration also
     * makes the extra IPTC-group read pointless, which is the one exiftool process this saves.
     *
     * @param array<string, mixed> $decodedTags flat read, which carries CodedCharacterSet itself
     *
     * @return string[]
     */
    private function resolveRecoverableTagNames(string $filePath, array $decodedTags): array
    {
        if (false === $this->isCharsetRecoveryEnabled()) {
            return [];
        }

        $declaredCharset = $decodedTags[self::CODED_CHARACTER_SET] ?? null;
        if (is_string($declaredCharset) && App::EMPTY_STRING !== $declaredCharset) {
            return [];
        }

        return $this->readIptcTagNames($filePath);
    }

    /**
     * Charset recovery must only touch IPTC values and the flat read loses tag groups, so the IPTC
     * group is read alone for its tag names. Fails safe: on error no tag gets charset-recovered.
     *
     * @return string[]
     */
    private function readIptcTagNames(string $filePath): array
    {
        try {
            $decoded = $this->decodeTags(
                $this->execute($filePath, [...self::READ_TAGS, ...self::IPTC_PASSTHROUGH, '-IPTC:all'])
            ) ?? [];
        } catch (RuntimeException $exception) {
            $this->damLogger->warning(DamLogger::NAMESPACE_EXIFTOOL, $exception->getMessage());
            $decoded = [];
        }
        unset($decoded['SourceFile']);

        return array_map(strval(...), array_keys($decoded));
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

    private function decodeTags(string $output): ?array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) && is_array($decoded[0] ?? null)
            ? $decoded[0]
            : null;
    }
}
