<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\ElevenlabsTtsProvider;
use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsVoiceFixtures;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\ElevenlabsClientMock;

/**
 * Verifies the ElevenLabs request-stitching gate: previous_request_ids is sent only for models on the
 * allowlist. eleven_v3 (and any unlisted model) must omit it so the API does not reject the request.
 */
final class ElevenlabsModelStitchingTest extends CoreDamKernelTestCase
{
    public function testAllowlistedModelSendsPreviousRequestIds(): void
    {
        [$voice, $extSystem] = $this->resolveDefaultElevenlabsVoice();
        // Default model (eleven_multilingual_v2) is on the allowlist.

        $body = $this->synthesizeAndCaptureBody($voice, $extSystem);

        self::assertSame(['req-a', 'req-b'], $body['previous_request_ids'] ?? null);
    }

    public function testV3ModelOmitsPreviousRequestIds(): void
    {
        [$voice, $extSystem] = $this->resolveDefaultElevenlabsVoice();
        $voice->setModelId('eleven_v3');

        $body = $this->synthesizeAndCaptureBody($voice, $extSystem);

        self::assertSame('eleven_v3', $body['model_id'] ?? null);
        self::assertArrayNotHasKey(
            'previous_request_ids',
            $body,
            'eleven_v3 must not receive previous_request_ids (the API rejects it with HTTP 400).',
        );
    }

    public function testSendsOutputFormatQueryParam(): void
    {
        [$voice, $extSystem] = $this->resolveDefaultElevenlabsVoice();
        $mock = $this->getService(ElevenlabsClientMock::class);
        $mock->sentQueries = [];

        $this->getService(ElevenlabsTtsProvider::class)
            ->synthesizeChunk('A short narration chunk.', $voice, $extSystem, []);

        $query = $mock->sentQueries[0] ?? null;
        self::assertIsArray($query, 'The provider must have called the ElevenLabs client.');
        self::assertSame('mp3_44100_128', $query['output_format'] ?? null);
    }

    /**
     * @return array{ElevenlabsVoice, ExtSystem}
     */
    private function resolveDefaultElevenlabsVoice(): array
    {
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $extSystem);

        $voice = $this->getService(VoiceResolver::class)->resolve(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG, $extSystem);
        self::assertInstanceOf(ElevenlabsVoice::class, $voice);

        return [$voice, $extSystem];
    }

    /**
     * @return array<string, mixed>
     */
    private function synthesizeAndCaptureBody(ElevenlabsVoice $voice, ExtSystem $extSystem): array
    {
        $mock = $this->getService(ElevenlabsClientMock::class);
        $mock->sentBodies = [];

        $this->getService(ElevenlabsTtsProvider::class)
            ->synthesizeChunk('A short narration chunk.', $voice, $extSystem, ['req-a', 'req-b']);

        self::assertNotEmpty($mock->sentBodies, 'The provider must have called the ElevenLabs client.');

        return $mock->sentBodies[array_key_last($mock->sentBodies)];
    }
}
