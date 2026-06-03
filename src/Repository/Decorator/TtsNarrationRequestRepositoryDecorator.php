<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestAssetFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestLicenceFilter;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;

final class TtsNarrationRequestRepositoryDecorator
{
    public function __construct(
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly TtsAssetRepository $ttsAssetRepository,
    ) {
    }

    /**
     * Scoped to one asset licence (path); the asset custom filter stays registered so the CMS can narrow
     * to a single article's asset via api params.
     */
    public function findByLicence(ApiParams $apiParams, AssetLicence $licence): ApiResponseList
    {
        return $this->requestRepository->findByApiParams(
            apiParams: TtsNarrationRequestLicenceFilter::applyTo($apiParams, $licence),
            customFilters: [new TtsNarrationRequestLicenceFilter(), new TtsNarrationRequestAssetFilter()],
        );
    }

    public function findByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): ApiResponseList
    {
        return $this->requestRepository->findByApiParams(
            apiParams: TtsNarrationRequestExtSystemFilter::applyTo($apiParams, $extSystem),
            customFilters: [new TtsNarrationRequestExtSystemFilter()],
        );
    }

    /**
     * Enriches the request with its produced {@see TtsAsset} (joined, transient) for the detail response.
     */
    public function getDetail(TtsNarrationRequest $request): TtsNarrationRequest
    {
        $assetId = $request->getAssetId();

        return $request->setTtsAsset(
            null !== $assetId ? $this->ttsAssetRepository->findByAssetIdJoined($assetId) : null,
        );
    }
}
