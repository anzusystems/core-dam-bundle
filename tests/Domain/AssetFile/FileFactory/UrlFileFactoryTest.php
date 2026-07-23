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

// IP literals throughout: NoPrivateNetworkHttpClient blocks unresolvable hosts and literals need no DNS.
final class UrlFileFactoryTest extends CoreDamKernelTestCase
{
    private const string TRUSTED_DOMAINS = '.sme.sk, trusted.example, 8.8.4.4, 127.0.0.1';

    /**
     * @var list<array{url: string, options: array}>
     */
    private array $requests = [];

    // The guard wrapper follows redirects itself (per-hop IP check), so the cap is asserted in dev mode below.
    public function testTrustedPublicIpDownloadsOverHttpThroughTheGuard(): void
    {
        $file = $this->factory()->downloadFile('http://8.8.4.4/audio.mp3');

        self::assertFileExists((string) $file->getRealPath());
        self::assertCount(1, $this->requests);
    }

    public function testTrustedPrivateIpBlockedByDefault(): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        try {
            $this->factory()->downloadFile('https://127.0.0.1/a.mp3');
        } finally {
            self::assertCount(0, $this->requests);
        }
    }

    public function testDevFlagAllowsPrivateNetworkForTrustedAndCapsRedirects(): void
    {
        $this->factory(allowPrivateNetworks: true)->downloadFile('http://127.0.0.1/a.mp3');

        self::assertCount(1, $this->requests);
        self::assertSame(UrlFileFactory::TRUSTED_MAX_REDIRECTS, $this->requests[0]['options']['max_redirects']);
    }

    // Matcher logic only — hostnames don't resolve, so the raw dev-mode client keeps this deterministic.
    public function testTrustedWildcardMatchesSubdomainAndBareDomain(): void
    {
        $this->factory(allowPrivateNetworks: true)->downloadFile('http://audio.sme.sk/a.mp3');
        $this->factory(allowPrivateNetworks: true)->downloadFile('http://sme.sk/b.mp3');

        self::assertCount(2, $this->requests);
    }

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
        yield 'untrusted private ip' => ['https://10.0.0.5/a.mp3'];
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

    private function factory(?MockResponse $response = null, bool $allowPrivateNetworks = false): UrlFileFactory
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
            urlFileAllowPrivateNetworks: $allowPrivateNetworks,
        );
    }
}
