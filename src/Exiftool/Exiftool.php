<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

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

    public function __construct(
        private readonly string $exiftoolBin,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function getTags(string $filePath): array
    {
        try {
            return $this->parseOutput($this->execute($filePath, self::READ_TAGS));
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
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(trim((string) $tagValue));

                continue;
            }
            // List tags (Keywords, Subject) come back as JSON arrays; keep the flat form consumers already expect.
            if (is_array($tagValue)) {
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(implode(', ', array_filter($tagValue, 'is_scalar')));
            }
        }

        return $tagList;
    }

    private function decodeTags(string $output): ?array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) && is_array($decoded[0] ?? null)
            ? $decoded[0]
            : null;
    }
}
