<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;

final readonly class TtsNarrationRequestFactory
{
    public function createInitial(
        TtsSynthesizeRequestDto $dto,
        AssetLicence $licence,
        string $shellAssetId,
        string $initialIdempotencyKey,
    ): TtsNarrationRequest {
        return $this->applyContent(new TtsNarrationRequest()->setMode(TtsRequestMode::Initial), $dto, $licence)
            ->setInitialIdempotencyKey($initialIdempotencyKey)
            ->setAssetId($shellAssetId);
    }

    public function createRegenerate(TtsSynthesizeRequestDto $dto, AssetLicence $licence, string $stableAssetId): TtsNarrationRequest
    {
        return $this->applyContent(new TtsNarrationRequest()->setMode(TtsRequestMode::Regenerate), $dto, $licence)
            ->setAssetId($stableAssetId);
    }

    private function applyContent(TtsNarrationRequest $request, TtsSynthesizeRequestDto $dto, AssetLicence $licence): TtsNarrationRequest
    {
        return $request
            ->setVoiceFamilySlug($dto->getVoiceFamilySlug())
            ->setTitle($dto->getTitle())
            ->setDescription($dto->getDescription())
            ->setKeywords($dto->getKeywords())
            ->setAuthors($dto->getAuthors())
            ->setExtSystemId($licence->getExtSystem()->getId())
            ->setAssetLicence($licence)
            ->setPodcastIds($dto->getPodcasts()->map(static fn (Podcast $podcast): string => (string) $podcast->getId())->toArray())
            ->setMainImageFileId($dto->getMainImageFileId())
            ->setSourceText($dto->getText());
    }
}
