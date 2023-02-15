<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Event\DistributionAuthorized;
use AnzuSystems\CoreDamBundle\Exception\YoutubeAuthorizationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\AccessTokenDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\ExchangeCodeStateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\RefreshTokenDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\TokenResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeCodeDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Exception as YoutubeException;
use Google\Exception;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

final class YoutubeAuthenticator
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly YoutubeExchangeCodeStateManager $exchangeCodeStateManager,
        private readonly GoogleClientProvider $clientProvider,
        private readonly CurrentAnzuUserProvider $currentUserProvider,
        private readonly TokenStorage $tokenStorage,
    ) {
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function isAuthenticated(string $distributionService): bool
    {
        return $this->tokenStorage->hasRefreshToken($distributionService);
    }

    /**
     * @throws Exception
     * @throws PsrInvalidArgumentException
     * @throws YoutubeException
     */
    public function generateAuthUrl(string $distributionService): string
    {
        $client = $this->clientProvider->getClient($distributionService);

        $user = $this->currentUserProvider->getCurrentUser();
        $exchangeToken = $this->exchangeCodeStateManager->generateExchangeCodeStateDto(
            $distributionService,
            (int) $user->getId(),
            (int) $user->getId()
        );

        $this->tokenStorage->storeExchangeTokenState($exchangeToken);
        $client->setState($exchangeToken->getState());

        return $client->createAuthUrl();
    }

    /**
     * @throws Exception
     * @throws PsrInvalidArgumentException
     * @throws SerializerException
     * @throws YoutubeAuthorizationException
     */
    public function authorizeCode(YoutubeCodeDto $youtubeCodeDto): string
    {
        $exchangeToken = $this->getExchangeCodeStateDto($youtubeCodeDto);
        $accessTokenResponse = $this->exchangeCodeForAccessToken($exchangeToken, $youtubeCodeDto);

        $this->dispatcher->dispatch(
            new DistributionAuthorized(
                $accessTokenResponse->getServiceId(),
                $exchangeToken->getInitiatorId(),
            )
        );

        return $accessTokenResponse->getServiceId();
    }

    /**
     * @throws PsrInvalidArgumentException
     * @throws YoutubeAuthorizationException
     */
    public function getExchangeCodeStateDto(YoutubeCodeDto $youtubeCodeDto): ExchangeCodeStateDto
    {
        $exchangeCodeStateDto = $this->tokenStorage->popExchangeTokenState(
            $youtubeCodeDto->getState()
        );
        if (null === $exchangeCodeStateDto) {
            throw new YoutubeAuthorizationException(YoutubeAuthorizationException::INVALID_EXCHANGE_TOKEN_STATE);
        }

        return $exchangeCodeStateDto;
    }

    /**
     * @throws Exception
     * @throws PsrInvalidArgumentException
     * @throws SerializerException
     * @throws YoutubeAuthorizationException
     */
    public function getAccessToken(
        string $serviceId
    ): AccessTokenDto {
        $accessToken = $this->tokenStorage->getAccessToken($serviceId);
        if ($accessToken) {
            return $accessToken;
        }

        $refreshToken = $this->tokenStorage->getRefreshToken($serviceId);
        if (null === $refreshToken) {
            throw new YoutubeAuthorizationException(YoutubeAuthorizationException::NOT_AUTHORIZED_MESSAGE);
        }

        return $this->refreshAccessToken($refreshToken);
    }

    /**
     * @throws Exception
     * @throws PsrInvalidArgumentException
     * @throws SerializerException
     * @throws SerializerException
     */
    public function exchangeCodeForAccessToken(
        ExchangeCodeStateDto $exchangeCodeStateDto,
        YoutubeCodeDto $youtubeCodeDto
    ): AccessTokenDto {
        $tokenRaw = $this->clientProvider
            ->getClient($exchangeCodeStateDto->getService())
            ->fetchAccessTokenWithAuthCode($youtubeCodeDto->getCode());

        /** @var TokenResponseDto $token */
        $token = $this->serializer->fromArray($tokenRaw, TokenResponseDto::class);

        return $this->storeTokens(
            $exchangeCodeStateDto->getService(),
            $token
        );
    }

    /**
     * @throws Exception
     * @throws PsrInvalidArgumentException
     * @throws SerializerException
     */
    private function refreshAccessToken(
        RefreshTokenDto $refreshTokenDto
    ): AccessTokenDto {
        $tokenRaw = $this->clientProvider
            ->getClient($refreshTokenDto->getServiceId())
            ->fetchAccessTokenWithRefreshToken($refreshTokenDto->getRefreshToken());

        /** @var TokenResponseDto $token */
        $token = $this->serializer->fromArray($tokenRaw, TokenResponseDto::class);

        return $this->storeTokens(
            $refreshTokenDto->getServiceId(),
            $token
        );
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    private function storeTokens(string $serviceId, TokenResponseDto $tokenResponse): AccessTokenDto
    {
        $this->validateTokenResponse($tokenResponse);

        $this->tokenStorage->storeRefreshToken(
            RefreshTokenDto::createFromTokenResponse(
                serviceId: $serviceId,
                dto: $tokenResponse
            )
        );

        return $this->tokenStorage->storeAccessToken(
            AccessTokenDto::createFromTokenResponse(
                serviceId: $serviceId,
                dto: $tokenResponse
            )
        );
    }

    private function validateTokenResponse(TokenResponseDto $tokenResponse): void
    {
        $missingScopes = array_diff(
            explode(' ', $tokenResponse->getScope()),
            GoogleClientProvider::REQUIRED_SCOPES
        );

        if (false === empty($missingScopes)) {
            throw new YoutubeAuthorizationException(YoutubeAuthorizationException::MISSING_SCOPE_MESSAGE);
        }

        if (empty($tokenResponse->getRefreshToken())) {
            throw new YoutubeAuthorizationException(YoutubeAuthorizationException::MISSING_REFRESH_TOKEN_MESSAGE);
        }

        if (empty($tokenResponse->getAccessToken())) {
            throw new YoutubeAuthorizationException(YoutubeAuthorizationException::MISSING_ACCESS_TOKEN_MESSAGE);
        }
    }
}
