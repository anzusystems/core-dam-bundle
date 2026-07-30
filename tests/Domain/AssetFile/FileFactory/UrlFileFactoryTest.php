<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\UrlFileFactory;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
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
        self::assertSame(UrlFileFactory::MAX_REDIRECTS, $this->requests[0]['options']['max_redirects']);
    }

    // Matcher logic only — hostnames don't resolve, so the raw dev-mode client keeps this deterministic.
    public function testTrustedWildcardMatchesSubdomainAndBareDomain(): void
    {
        $this->factory(allowPrivateNetworks: true)->downloadFile('http://audio.sme.sk/a.mp3');
        $this->factory(allowPrivateNetworks: true)->downloadFile('http://sme.sk/b.mp3');

        self::assertCount(2, $this->requests);
    }

    // Podcast enclosures (traffic.omny.fm → CDN) are untrusted redirect trackers; refusing to follow them
    // wrote the empty 302 body as the audio file and the asset failed later on invalid_mime_type.
    public function testUntrustedRedirectIsFollowedToThePayload(): void
    {
        $file = $this->factory([
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://8.8.4.4/final.mp3']),
            new MockResponse('mp3-bytes', ['http_code' => 200]),
        ])->downloadFile('https://8.8.8.8/a.mp3');

        self::assertCount(2, $this->requests);
        self::assertSame('mp3-bytes', file_get_contents((string) $file->getRealPath()));
    }

    public function testUnfollowedRedirectFailsInsteadOfWritingEmptyFile(): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        $this->factory([new MockResponse('', ['http_code' => 302])])->downloadFile('https://8.8.8.8/a.mp3');
    }

    public function testUntrustedHttpsDowngradeOnRedirectIsRejected(): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        $this->factory([
            new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://8.8.4.4/final.mp3']),
            new MockResponse('mp3-bytes', ['http_code' => 200]),
        ])->downloadFile('https://8.8.8.8/a.mp3');
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

    public function testDownloadSizeCapAbortsOversizedTransfer(): void
    {
        $this->factory()->downloadFile('https://8.8.8.8/a.mp3');

        $onProgress = $this->requests[0]['options']['on_progress'];
        self::assertIsCallable($onProgress);
        $onProgress(1_024, UrlFileFactory::MAX_DOWNLOAD_BYTES, []);

        $this->expectException(RuntimeException::class);
        $onProgress(1_024, UrlFileFactory::MAX_DOWNLOAD_BYTES + 1, []);
    }

    public function testErrorStatusFails(): void
    {
        $this->expectException(AssetFileProcessFailed::class);

        $this->factory([new MockResponse('', ['http_code' => 404])])->downloadFile('https://8.8.8.8/a.mp3');
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function factory(array $responses = [], bool $allowPrivateNetworks = false): UrlFileFactory
    {
        $queue = $responses;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $this->requests[] = ['url' => $url, 'options' => $options];

            return array_shift($queue) ?? new MockResponse('mp3-bytes', ['http_code' => 200]);
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
