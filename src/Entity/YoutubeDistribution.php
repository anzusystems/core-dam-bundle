<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Embeds\YoutubeFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\YoutubeTexts;
use AnzuSystems\CoreDamBundle\Model\Enum\YoutubeVideoPrivacy;
use AnzuSystems\CoreDamBundle\Repository\YoutubeDistributionRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: YoutubeDistributionRepository::class)]
#[AppAssert\Youtube]
class YoutubeDistribution extends Distribution
{
    #[ORM\Embedded(YoutubeTexts::class)]
    #[Assert\Valid]
    #[Serialize]
    protected YoutubeTexts $texts;

    #[ORM\Embedded(YoutubeFlags::class)]
    #[Assert\Valid]
    #[Serialize]
    protected YoutubeFlags $flags;

    #[ORM\Column(enumType: YoutubeVideoPrivacy::class)]
    #[Serialize]
    private YoutubeVideoPrivacy $privacy;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $channelId;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Serialize]
    private string $playlist;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Serialize]
    private string $language;

    public function __construct()
    {
        parent::__construct();
        $this->setChannelId('');
        $this->setPrivacy(YoutubeVideoPrivacy::Default);
        $this->setTexts(new YoutubeTexts());
        $this->setFlags(new YoutubeFlags());
        $this->setPlaylist('');
        $this->setLanguage('');
    }

    public function getPrivacy(): YoutubeVideoPrivacy
    {
        return $this->privacy;
    }

    public function setPrivacy(YoutubeVideoPrivacy $privacy): self
    {
        $this->privacy = $privacy;

        return $this;
    }

    #[Serialize]
    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): self
    {
        $this->channelId = $channelId;

        return $this;
    }

    public function getTexts(): YoutubeTexts
    {
        return $this->texts;
    }

    public function setTexts(YoutubeTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getFlags(): YoutubeFlags
    {
        return $this->flags;
    }

    public function setFlags(YoutubeFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getPlaylist(): string
    {
        return $this->playlist;
    }

    public function setPlaylist(string $playlist): self
    {
        $this->playlist = $playlist;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }
}
