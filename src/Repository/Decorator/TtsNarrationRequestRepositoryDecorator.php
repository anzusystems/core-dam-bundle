<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\TtsNarrationRequestApiParams;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsChunkProgress;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestAssetFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestLicenceFilter;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;

final class TtsNarrationRequestRepositoryDecorator
{
    public function __construct(
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly TtsAssetRepository $ttsAssetRepository,
        private readonly TtsSynthesisChunkRepository $chunkRepository,
    ) {
    }

    /**
     * Asset filter is always registered so CMS can narrow to a single asset.
     */
    public function findByLicence(ApiParams $apiParams, AssetLicence $licence): ApiResponseList
    {
        return $this->requestRepository->findByApiParams(
            apiParams: TtsNarrationRequestApiParams::applyLicenceCustomFilter($apiParams, $licence),
            customFilters: [new TtsNarrationRequestLicenceFilter(), new TtsNarrationRequestAssetFilter()],
        );
    }

    public function findByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): ApiResponseList
    {
        return $this->requestRepository->findByApiParams(
            apiParams: TtsNarrationRequestApiParams::applyExtSystemCustomFilter($apiParams, $extSystem),
            customFilters: [new TtsNarrationRequestExtSystemFilter()],
        );
    }

    /**
     * Enriches the request with its TtsAsset and chunk progress for the detail response.
     */
    public function getDetail(TtsNarrationRequest $request): TtsNarrationRequest
    {
        $assetId = $request->getAssetId();
        $request->setTtsAsset(
            null !== $assetId ? $this->ttsAssetRepository->findByAssetIdJoined($assetId) : null,
        );

        // Absent on single-run requests (no chunk rows).
        $counts = $this->chunkRepository->progressCounts((string) $request->getId());
        if ($counts['total'] > 0) {
            $request->setChunkProgress(TtsChunkProgress::fromCounts($counts['total'], $counts['done'], $counts['failed']));
        }

        return $request;
    }
}
