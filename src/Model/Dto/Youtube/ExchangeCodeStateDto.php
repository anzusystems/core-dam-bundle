<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class ExchangeCodeStateDto
{
    #[Serialize]
    private string $state = '';

    #[Serialize]
    private string $service = '';

    #[Serialize]
    private int $userId = 0;

    #[Serialize]
    private int $initiatorId = 0;

    #[Serialize]
    private DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->expiresAt = App::getAppDate();
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getInitiatorId(): int
    {
        return $this->initiatorId;
    }

    public function setInitiatorId(int $initiatorId): self
    {
        $this->initiatorId = $initiatorId;

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
