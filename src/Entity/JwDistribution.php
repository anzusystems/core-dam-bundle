<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\JwTexts;
use AnzuSystems\CoreDamBundle\Repository\JwDistributionRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JwDistributionRepository::class)]
class JwDistribution extends Distribution
{
    public const string DISCRIMINATOR = 'jwDistribution';

    #[ORM\Embedded(JwTexts::class)]
    #[Assert\Valid]
    #[Serialize]
    protected JwTexts $texts;

    #[ORM\Column(type: Types::STRING, length: 2_048, options: ['default' => App::EMPTY_STRING])]
    private string $directSourceUrl = App::EMPTY_STRING;

    public function __construct()
    {
        parent::__construct();
        $this->setTexts(new JwTexts());
    }

    public function getTexts(): JwTexts
    {
        return $this->texts;
    }

    public function setTexts(JwTexts $texts): self
    {
        $this->texts = $texts;

        return $this;
    }

    public function getDirectSourceUrl(): string
    {
        return $this->directSourceUrl;
    }

    public function setDirectSourceUrl(string $directSourceUrl): self
    {
        $this->directSourceUrl = $directSourceUrl;
        return $this;
    }

    public function getDiscriminator(): string
    {
        return self::DISCRIMINATOR;
    }
}
