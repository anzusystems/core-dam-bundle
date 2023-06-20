<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\AccessTokenDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\ExchangeCodeStateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\RefreshTokenDto;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

final class TokenStorage
{
    use SerializerAwareTrait;

    private const ACCESS_TOKEN_KEY = 'access_token';
    private const REFRESH_TOKEN_KEY = 'refresh_token';
    private const EXCHANGE_CODE_STATE_KEY = 'exchange_code_state';

    public function __construct(
        private readonly CacheItemPoolInterface $coreDamBundleYoutubeCache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearTokens(string $serviceId): void
    {
        $this->coreDamBundleYoutubeCache->deleteItems([
            $this->getRefreshTokenName($serviceId),
            $this->getAccessTokenName($serviceId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function storeAccessToken(AccessTokenDto $accessToken): AccessTokenDto
    {
        $item = $this->coreDamBundleYoutubeCache->getItem(
            key: $this->getAccessTokenName($accessToken->getServiceId())
        );
        $item->set($accessToken)
            ->expiresAt($accessToken->getExpiresAtWithThreshold());
        $this->coreDamBundleYoutubeCache->save($item);

        return $accessToken;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function storeRefreshToken(RefreshTokenDto $refreshToken): RefreshTokenDto
    {
        $item = $this->coreDamBundleYoutubeCache->getItem(
            key: $this->getRefreshTokenName($refreshToken->getServiceId())
        );
        $item->set($refreshToken)
            ->expiresAt($refreshToken->getExpiresAt());
        $this->coreDamBundleYoutubeCache->save($item);

        return $refreshToken;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getAccessToken(string $serviceId): ?AccessTokenDto
    {
        $item = $this->coreDamBundleYoutubeCache->getItem(
            $this->getAccessTokenName($serviceId)
        );

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function hasRefreshToken(string $serviceId): bool
    {
        return $this->coreDamBundleYoutubeCache->hasItem(
            $this->getRefreshTokenName($serviceId)
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getRefreshToken(string $serviceId): ?RefreshTokenDto
    {
        $item = $this->coreDamBundleYoutubeCache->getItem(
            $this->getRefreshTokenName($serviceId)
        );

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function storeExchangeTokenState(ExchangeCodeStateDto $exchangeCodeStateDto): void
    {
        $item = $this->coreDamBundleYoutubeCache->getItem(
            $this->getExchangeCodeStateName($exchangeCodeStateDto->getState())
        );
        $item->set($exchangeCodeStateDto);
        $item->expiresAt($exchangeCodeStateDto->getExpiresAt());
        $this->coreDamBundleYoutubeCache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function popExchangeTokenState(string $state): ?ExchangeCodeStateDto
    {
        $key = $this->getExchangeCodeStateName($state);
        $item = $this->coreDamBundleYoutubeCache->getItem(
            $key
        );
        if ($item->isHit()) {
            $this->coreDamBundleYoutubeCache->deleteItem($key);

            return $item->get();
        }

        return null;
    }

    private function getAccessTokenName(string $serviceId): string
    {
        return "yt_access_token_{$serviceId}";
    }

    private function getRefreshTokenName(string $serviceId): string
    {
        return "yt_refresh_token_{$serviceId}";
    }

    private function getExchangeCodeStateName(string $state): string
    {
        return "yt_exchange_token_state_{$state}";
    }
}
