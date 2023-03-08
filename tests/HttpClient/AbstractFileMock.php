<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use Symfony\Component\HttpClient\MockHttpClient;

abstract class AbstractFileMock extends MockHttpClient
{
    private const TEST_DATA_FILES = '/tests/data/files/';

    protected function getTestDataFile(string $file): string
    {
        return file_get_contents($this->getPath($file));
    }

    protected function getTestDataMime(string $file): string
    {
        return (string) mime_content_type($this->getPath($file));
    }

    protected function getPath(string $file): string
    {
        return App::getProjectDir() . self::TEST_DATA_FILES . $file;
    }
}
