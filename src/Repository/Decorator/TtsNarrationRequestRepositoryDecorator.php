<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestAssetFilter;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;

final class TtsNarrationRequestRepositoryDecorator
{
    public function __construct(
        private readonly TtsNarrationRequestRepository $requestRepository,
        private readonly TtsAssetRepository $ttsAssetRepository,
    ) {
    }

    public function findByAsset(ApiParams $apiParams, Asset $asset): ApiResponseList
    {
        return $this->requestRepository->findByApiParams(
            apiParams: TtsNarrationRequestAssetFilter::applyTo($apiParams, $asset),
            customFilters: [new TtsNarrationRequestAssetFilter()],
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
