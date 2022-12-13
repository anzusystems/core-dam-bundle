<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Embeds\JwTexts;
use AnzuSystems\CoreDamBundle\Repository\JwDistributionRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JwDistributionRepository::class)]
class JwDistribution extends Distribution
{
    public const THUMBNAIL_DATA = 'thumbnail';

    #[ORM\Embedded(JwTexts::class)]
    #[Assert\Valid]
    #[Serialize]
    protected JwTexts $texts;

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
}
