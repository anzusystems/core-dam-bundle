<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\App;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetDates
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $uploadedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expireAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishAt;

    public function __construct()
    {
        $this->setUploadedAt(App::getAppDate());
        $this->setExpireAt(null);
        $this->setPublishAt(null);
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getExpireAt(): ?DateTimeImmutable
    {
        return $this->expireAt;
    }

    public function setExpireAt(?DateTimeImmutable $expireAt): self
    {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getPublishAt(): ?DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function setPublishAt(?DateTimeImmutable $publishAt): self
    {
        $this->publishAt = $publishAt;

        return $this;
    }
}
