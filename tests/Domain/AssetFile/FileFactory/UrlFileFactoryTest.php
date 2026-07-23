<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\UrlFileFactory;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UrlFileFactoryTest extends CoreDamKernelTestCase
{
    private const string TRUSTED_DOMAINS = '.sme.sk, trusted.example';

    /**
     * @var list<array{url: string, options: array}>
     */
    private array $requests = [];

    public function testTrustedExactMatchDownloadsOverHttp(): void
    {
        $file = $this->factory()->downloadFile('http://trusted.example/audio.mp3');

        self::assertFileExists((string) $file->getRealPath());
        self::assertCount(1, $this->requests);
        self::assertSame(UrlFileFactory::TRUSTED_MAX_REDIRECTS, $this->requests[0]['options']['max_redirects']);
    }

    public function testTrustedWildcardMatchesSubdomainAndBareDomain(): void
    {
        $this->factory()->downloadFile('http://audio.sme.sk/a.mp3');
        $this->factory()->downloadFile('http://sme.sk/b.mp3');

        self::assertCount(2, $this->requests);
    }

    // Public IP literals: NoPrivateNetworkHttpClient blocks unresolvable hosts, and literals need no DNS.
    public function testUntrustedHttpsGetsZeroRedirects(): void
    {
        $this->factory()->downloadFile('https://8.8.8.8/a.mp3');

        self::assertCount(1, $this->requests);
        self::assertSame(0, $this->requests[0]['options']['max_redirects']);
    }

    #[DataProvider('provideRejectedUrls')]
    public function testRejectedBeforeAnyRequest(string $url): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        try {
            $this->factory()->downloadFile($url);
        } finally {
            self::assertCount(0, $this->requests);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectedUrls(): iterable
    {
        yield 'untrusted http' => ['http://evil.example/a.mp3'];
        yield 'untrusted private ip' => ['https://127.0.0.1/a.mp3'];
        yield 'suffix without dot boundary' => ['http://evilsme.sk/a.mp3'];
        yield 'trusted domain as subdomain of attacker' => ['http://sme.sk.evil.example/a.mp3'];
        yield 'trusted domain in userinfo' => ['http://trusted.example@evil.example/a.mp3'];
        yield 'malformed url' => ['http:///nohost'];
    }

    public function testUppercaseHttpsSchemeIsAcceptedForUntrusted(): void
    {
        $this->factory()->downloadFile('HTTPS://8.8.8.8/a.mp3');

        self::assertCount(1, $this->requests);
    }

    public function testErrorStatusFails(): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        $this->factory(new MockResponse('', ['http_code' => 404]))->downloadFile('https://8.8.8.8/a.mp3');
    }

    private function factory(?MockResponse $response = null): UrlFileFactory
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = ['url' => $url, 'options' => $options];

            return $response ?? new MockResponse('mp3-bytes', ['http_code' => 200]);
        });

        return new UrlFileFactory(
            fileSystemProvider: $this->getService(FileSystemProvider::class),
            client: $client,
            damLogger: $this->getService(DamLogger::class),
            appLogger: new NullLogger(),
            urlFileTrustedDomains: self::TRUSTED_DOMAINS,
        );
    }
}
