<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchResult;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchStatus;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DispatchResult factory methods and SynthesizeResponseDto.assetId contract.
 */
final class DispatchResultTest extends TestCase
{
    public function testPendingCarriesStableAssetIdAndEntity(): void
    {
        $requestId = 'req-uuid-1';
        $assetId = 'asset-uuid-stable';
        $narrationRequest = new TtsNarrationRequest();

        $result = DispatchResult::pending($requestId, $assetId, $narrationRequest);

        $this->assertSame(DispatchStatus::Pending, $result->status);
        $this->assertSame($requestId, $result->requestId);
        $this->assertNotNull($result->getAssetId(), 'Pending result must expose a non-null assetId');
        $this->assertSame($assetId, $result->getAssetId());
        $this->assertSame($narrationRequest, $result->narrationRequest);
    }

    public function testAlreadyPendingHasNoAssetId(): void
    {
        $result = DispatchResult::alreadyPending();

        $this->assertSame(DispatchStatus::AlreadyPending, $result->status);
        $this->assertNull($result->getAssetId(), 'AlreadyPending is a duplicate no-op — a concurrent dispatch owns the media attach');
        $this->assertNull($result->narrationRequest);
    }

    public function testAlreadyExistsCarriesExistingAssetId(): void
    {
        $existingAssetId = 'existing-active-asset-uuid';

        $result = DispatchResult::alreadyExists($existingAssetId);

        $this->assertSame(DispatchStatus::AlreadyExists, $result->status);
        $this->assertSame($existingAssetId, $result->existingAssetId);
        $this->assertNotNull($result->getAssetId(), 'AlreadyExists result must expose a non-null assetId via getAssetId()');
        $this->assertSame($existingAssetId, $result->getAssetId());
    }

    public function testSynthesizeResponseDtoFromPendingResult(): void
    {
        $result = DispatchResult::pending('req-1', 'asset-1', new TtsNarrationRequest());
        $dto = SynthesizeResponseDto::fromResult($result);

        $this->assertSame('req-1', $dto->getRequestId());
        $this->assertSame('asset-1', $dto->getAssetId());
        $this->assertSame(DispatchStatus::Pending, $dto->getStatus());
    }

    public function testSynthesizeResponseDtoFromAlreadyPendingResult(): void
    {
        $result = DispatchResult::alreadyPending();
        $dto = SynthesizeResponseDto::fromResult($result);

        $this->assertNull($dto->getRequestId());
        $this->assertNull($dto->getAssetId());
        $this->assertSame(DispatchStatus::AlreadyPending, $dto->getStatus());
    }

    public function testSynthesizeResponseDtoFromAlreadyExistsResult(): void
    {
        $result = DispatchResult::alreadyExists('asset-existing');
        $dto = SynthesizeResponseDto::fromResult($result);

        $this->assertSame('asset-existing', $dto->getExistingAssetId());
        $this->assertSame('asset-existing', $dto->getAssetId());
        $this->assertSame(DispatchStatus::AlreadyExists, $dto->getStatus());
    }
}
