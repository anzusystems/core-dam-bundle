<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegionOfInterestRepository::class)]
#[ORM\Index(fields: ['position'], name: 'IDX_position')]
class RegionOfInterest implements UuidIdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, PositionableInterface, AssetLicenceInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use PositionTrait;
    use UserTrackingTrait;

    public const FIRST_ROI_POSITION = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pointX;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pointY;

    #[ORM\Column(type: Types::FLOAT)]
    private float $percentageWidth;

    #[ORM\Column(type: Types::FLOAT)]
    private float $percentageHeight;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: ImageFile::class, inversedBy: 'regionsOfInterest')]
    private ImageFile $image;

    public function __construct()
    {
        $this->setPointX(0);
        $this->setPointY(0);
        $this->setPercentageWidth(0.0);
        $this->setPercentageHeight(0.0);
        $this->setTitle('');
    }

    public function getPointX(): int
    {
        return $this->pointX;
    }

    public function setPointX(int $pointX): self
    {
        $this->pointX = $pointX;

        return $this;
    }

    public function getPointY(): int
    {
        return $this->pointY;
    }

    public function setPointY(int $pointY): self
    {
        $this->pointY = $pointY;

        return $this;
    }

    public function getPercentageWidth(): float
    {
        return $this->percentageWidth;
    }

    public function setPercentageWidth(float $percentageWidth): self
    {
        $this->percentageWidth = $percentageWidth;

        return $this;
    }

    public function getPercentageHeight(): float
    {
        return $this->percentageHeight;
    }

    public function setPercentageHeight(float $percentageHeight): self
    {
        $this->percentageHeight = $percentageHeight;

        return $this;
    }

    public function setImage(ImageFile $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getImage(): ImageFile
    {
        return $this->image;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->image->getLicence();
    }
}
