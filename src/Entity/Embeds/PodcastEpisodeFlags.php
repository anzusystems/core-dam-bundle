<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PodcastEpisodeFlags
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $fromRss;

    #[Serialize]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $webPublicExportEnabled;

    #[Serialize]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $mobilePublicExportEnabled;

    public function __construct()
    {
        $this->setFromRss(false);
        $this->setWebPublicExportEnabled(false);
        $this->setMobilePublicExportEnabled(false);
    }

    #[Serialize]
    public function isFromRss(): bool
    {
        return $this->fromRss;
    }

    public function setFromRss(bool $fromRss): self
    {
        $this->fromRss = $fromRss;

        return $this;
    }

    public function isWebPublicExportEnabled(): bool
    {
        return $this->webPublicExportEnabled;
    }

    public function setWebPublicExportEnabled(bool $webPublicExportEnabled): self
    {
        $this->webPublicExportEnabled = $webPublicExportEnabled;

        return $this;
    }

    public function isMobilePublicExportEnabled(): bool
    {
        return $this->mobilePublicExportEnabled;
    }

    public function setMobilePublicExportEnabled(bool $mobilePublicExportEnabled): self
    {
        $this->mobilePublicExportEnabled = $mobilePublicExportEnabled;

        return $this;
    }
}
