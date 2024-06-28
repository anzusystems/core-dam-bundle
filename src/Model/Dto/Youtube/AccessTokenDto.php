<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\CoreDamBundle\App;
use DateTimeImmutable;

final class AccessTokenDto implements ScopeInterface
{
    private const int ACCESS_TOKEN_TRESHOLD = 300;

    private string $accessToken = '';
    private string $scope = '';
    private string $serviceId = '';
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $expiresAtWithThreshold;

    public function __construct()
    {
        $this->setExpiresAt(App::getAppDate());
    }

    public static function createFromTokenResponse(string $serviceId, TokenResponseDto $dto): self
    {
        $expiresTimestamp = $dto->getCreated() + $dto->getExpiresIn();

        return (new self())
            ->setAccessToken($dto->getAccessToken())
            ->setScope($dto->getScope())
            ->setExpiresAt(
                (new DateTimeImmutable())
                    ->setTimestamp($expiresTimestamp)
            )
            ->setExpiresAtWithThreshold(
                (new DateTimeImmutable())
                    ->setTimestamp($expiresTimestamp - self::ACCESS_TOKEN_TRESHOLD)
            )
            ->setServiceId($serviceId);
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

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

    public function getExpiresAtWithThreshold(): DateTimeImmutable
    {
        return $this->expiresAtWithThreshold;
    }

    public function setExpiresAtWithThreshold(DateTimeImmutable $expiresAtWithThreshold): self
    {
        $this->expiresAtWithThreshold = $expiresAtWithThreshold;

        return $this;
    }
}
