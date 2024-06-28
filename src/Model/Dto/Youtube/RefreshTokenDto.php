<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use DateTimeImmutable;

final class RefreshTokenDto implements ScopeInterface
{
    private const int REFRESH_TOKEN_CUSTOM_EXPIRATION = 7_776_000; // 90 days

    private string $refreshToken = '';
    private string $scope = '';
    private string $serviceId = '';
    private DateTimeImmutable $expiresAt;

    public static function createFromTokenResponse(string $serviceId, TokenResponseDto $dto): self
    {
        $expiresTimestamp = $dto->getCreated() + self::REFRESH_TOKEN_CUSTOM_EXPIRATION;

        return (new self())
            ->setRefreshToken($dto->getRefreshToken())
            ->setScope($dto->getScope())
            ->setExpiresAt(
                (new DateTimeImmutable())
                    ->setTimestamp($expiresTimestamp)
            )
            ->setServiceId($serviceId);
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
