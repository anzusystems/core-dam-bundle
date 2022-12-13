<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Repository\YoutubeDistributionRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[AppAssert\CustomData]
#[ORM\Entity(repositoryClass: YoutubeDistributionRepository::class)]
class CustomDistribution extends Distribution implements ResourceCustomFormProvidableInterface, CustomDataInterface
{
    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData;

    public function __construct()
    {
        parent::__construct();
        $this->setCustomData([]);
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function setCustomData(array $customData): static
    {
        $this->customData = $customData;

        return $this;
    }

    public function getResourceKey(): string
    {
        return CustomFormFactory::getDistributionServiceResourceKey($this->getDistributionService());
    }
}
