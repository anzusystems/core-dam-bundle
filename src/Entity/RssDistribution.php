<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Repository\RssDistributionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RssDistributionRepository::class)]
class RssDistribution extends Distribution
{
    #[ORM\Column(type: Types::STRING, length: 2_048)]
    private string $rssUrl;

    public function __construct()
    {
        parent::__construct();
        $this->setRssUrl('');
    }

    public function getRssUrl(): string
    {
        return $this->rssUrl;
    }

    public function setRssUrl(string $rssUrl): self
    {
        $this->rssUrl = $rssUrl;

        return $this;
    }
}
