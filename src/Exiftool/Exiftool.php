<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Process\Process;

final class Exiftool
{
    private const ALL = ['-all=', '-tagsFromFile', '@', '-StreamColor', '-StreamBitDepth', '-ColorSpace', '-Orientation'];
    private const PNG_CLEAR = ['-png:all=', '-overwrite_original'];
    private const DEFAULT_TIMEOUT = 15.0;


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
            return $this->parseOutput($this->execute($filePath));
        } catch (RuntimeException $exception) {
            $this->damLogger->error(DamLogger::NAMESPACE_EXIFTOOL, $exception->getMessage(), $exception);

            return [];
        }
    }

    public function clearPng(string $filePath): void
    {
        try {
            $this->execute($filePath, self::PNG_CLEAR);
        } catch (RuntimeException $exception) {
            $this->damLogger->error(DamLogger::NAMESPACE_EXIFTOOL, $exception->getMessage(), $exception);
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

    private function getErrorFromOutput(string $output): string
    {
        $tags = $this->parseOutput($output);

        return isset($tags['Error'])
            ? (string) $tags['Error']
            : '';
    }

    private function parseOutput(string $output): array
    {
        $tags = explode(PHP_EOL, $output);
        $tagList = [];

        foreach ($tags as $tag) {
            $tagPair = explode(':', $tag, 2);
            if (false === isset($tagPair[0]) || false === isset($tagPair[1])) {
                continue;
            }

            $tagName = preg_replace('/\s+/', '', trim($tagPair[0]));
            $tagValue = trim($tagPair[1]);
            $tagList[$tagName] = $tagValue;
        }

        return $tagList;
    }
}
