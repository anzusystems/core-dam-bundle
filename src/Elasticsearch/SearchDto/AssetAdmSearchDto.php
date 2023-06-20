<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageOrientation;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\ArrayStringHandler;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetAdmSearchDto extends AbstractSearchDto
{
    #[Serialize]
    protected string $text = '';

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $assetIds = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $podcastIds = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Choice(choices: AssetType::CHOICES, multiple: true, multipleMessage: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Count(max: 4, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $type = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Choice(choices: AssetStatus::CHOICES, multiple: true, multipleMessage: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Count(max: 3, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $status = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[AppAssert\Color(multiple: true)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $mostDominantColor = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[AppAssert\Color(multiple: true)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $closestMostDominantColor = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $codecName = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Choice(choices: ImageOrientation::OPTIONS, multiple: true, multipleMessage: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Count(max: 3, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $orientation = [];

    #[Serialize]
    protected ?bool $described = null;

    #[Serialize]
    protected ?bool $visible = null;

    #[Serialize]
    protected ?bool $generatedBySystem = null;

    #[Serialize]
    protected ?bool $inPodcast = null;

    #[Serialize]
    protected ?bool $fromRss = null;

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $slotNames = [];

    #[Serialize(handler: ArrayStringHandler::class)]
    #[Assert\Count(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected array $distributedInServices = [];

    /**
     * @var array<int, AssetLicence>
     */
    protected array $licences = [];

    #[Serialize]
    private ?int $shortestDimensionFrom = null;

    #[Serialize]
    private ?int $shortestDimensionUntil = null;

    #[Serialize]
    private ?int $pixelSizeFrom = null;

    #[Serialize]
    private ?int $pixelSizeUntil = null;

    #[Serialize]
    private ?int $widthFrom = null;

    #[Serialize]
    private ?int $widthUntil = null;

    #[Serialize]
    private ?int $heightFrom = null;

    #[Serialize]
    private ?int $heightUntil = null;

    #[Serialize]
    private ?int $ratioWidthFrom = null;

    #[Serialize]
    private ?int $ratioWidthUntil = null;

    #[Serialize]
    private ?int $ratioHeightFrom = null;

    #[Serialize]
    private ?int $ratioHeightUntil = null;

    #[Serialize]
    #[Assert\Range(notInRangeMessage: ValidationException::ERROR_FIELD_INVALID, min: 0, max: 360)]
    private ?int $rotationFrom = null;

    #[Serialize]
    #[Assert\Range(notInRangeMessage: ValidationException::ERROR_FIELD_INVALID, min: 0, max: 360)]
    private ?int $rotationUntil = null;

    #[Serialize]
    private ?int $durationFrom = null;

    #[Serialize]
    private ?int $durationUntil = null;

    #[Serialize]
    private ?int $bitrateFrom = null;

    #[Serialize]
    private ?int $bitrateUntil = null;

    #[Serialize]
    private ?int $slotsCountFrom = null;

    #[Serialize]
    private ?int $slotsCountUntil = null;

    #[Serialize]
    private ?DateTimeImmutable $createdAtFrom = null;

    #[Serialize]
    private ?DateTimeImmutable $createdAtUntil = null;

    public function getIndexName(): string
    {
        return Asset::getResourceName();
    }

    public function getAssetIds(): array
    {
        return $this->assetIds;
    }

    public function setAssetIds(array $assetIds): self
    {
        $this->assetIds = $assetIds;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getPodcastIds(): array
    {
        return $this->podcastIds;
    }

    public function setPodcastIds(array $podcastIds): self
    {
        $this->podcastIds = $podcastIds;

        return $this;
    }

    public function getType(): array
    {
        return $this->type;
    }

    public function setType(array $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): array
    {
        return $this->status;
    }

    public function setStatus(array $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMostDominantColor(): array
    {
        return $this->mostDominantColor;
    }

    public function setMostDominantColor(array $mostDominantColor): self
    {
        $this->mostDominantColor = $mostDominantColor;

        return $this;
    }

    public function getClosestMostDominantColor(): array
    {
        return $this->closestMostDominantColor;
    }

    public function setClosestMostDominantColor(array $closestMostDominantColor): self
    {
        $this->closestMostDominantColor = $closestMostDominantColor;

        return $this;
    }

    public function getCodecName(): array
    {
        return $this->codecName;
    }

    public function setCodecName(array $codecName): self
    {
        $this->codecName = $codecName;

        return $this;
    }

    public function getOrientation(): array
    {
        return $this->orientation;
    }

    public function setOrientation(array $orientation): self
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function isDescribed(): ?bool
    {
        return $this->described;
    }

    public function setDescribed(?bool $described): self
    {
        $this->described = $described;

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function isInPodcast(): ?bool
    {
        return $this->inPodcast;
    }

    public function setInPodcast(?bool $inPodcast): self
    {
        $this->inPodcast = $inPodcast;

        return $this;
    }

    public function getLicences(): array
    {
        return $this->licences;
    }

    public function setLicences(array $licences): self
    {
        $this->licences = $licences;

        return $this;
    }

    public function getPixelSizeFrom(): ?int
    {
        return $this->pixelSizeFrom;
    }

    public function setPixelSizeFrom(?int $pixelSizeFrom): self
    {
        $this->pixelSizeFrom = $pixelSizeFrom;

        return $this;
    }

    public function getPixelSizeUntil(): ?int
    {
        return $this->pixelSizeUntil;
    }

    public function setPixelSizeUntil(?int $pixelSizeUntil): self
    {
        $this->pixelSizeUntil = $pixelSizeUntil;

        return $this;
    }

    public function getWidthFrom(): ?int
    {
        return $this->widthFrom;
    }

    public function setWidthFrom(?int $widthFrom): self
    {
        $this->widthFrom = $widthFrom;

        return $this;
    }

    public function getWidthUntil(): ?int
    {
        return $this->widthUntil;
    }

    public function setWidthUntil(?int $widthUntil): self
    {
        $this->widthUntil = $widthUntil;

        return $this;
    }

    public function getHeightFrom(): ?int
    {
        return $this->heightFrom;
    }

    public function setHeightFrom(?int $heightFrom): self
    {
        $this->heightFrom = $heightFrom;

        return $this;
    }

    public function getHeightUntil(): ?int
    {
        return $this->heightUntil;
    }

    public function setHeightUntil(?int $heightUntil): self
    {
        $this->heightUntil = $heightUntil;

        return $this;
    }

    public function getRatioWidthFrom(): ?int
    {
        return $this->ratioWidthFrom;
    }

    public function setRatioWidthFrom(?int $ratioWidthFrom): self
    {
        $this->ratioWidthFrom = $ratioWidthFrom;

        return $this;
    }

    public function getRatioWidthUntil(): ?int
    {
        return $this->ratioWidthUntil;
    }

    public function setRatioWidthUntil(?int $ratioWidthUntil): self
    {
        $this->ratioWidthUntil = $ratioWidthUntil;

        return $this;
    }

    public function getRatioHeightFrom(): ?int
    {
        return $this->ratioHeightFrom;
    }

    public function setRatioHeightFrom(?int $ratioHeightFrom): self
    {
        $this->ratioHeightFrom = $ratioHeightFrom;

        return $this;
    }

    public function getRatioHeightUntil(): ?int
    {
        return $this->ratioHeightUntil;
    }

    public function setRatioHeightUntil(?int $ratioHeightUntil): self
    {
        $this->ratioHeightUntil = $ratioHeightUntil;

        return $this;
    }

    public function getRotationFrom(): ?int
    {
        return $this->rotationFrom;
    }

    public function setRotationFrom(?int $rotationFrom): self
    {
        $this->rotationFrom = $rotationFrom;

        return $this;
    }

    public function getRotationUntil(): ?int
    {
        return $this->rotationUntil;
    }

    public function setRotationUntil(?int $rotationUntil): self
    {
        $this->rotationUntil = $rotationUntil;

        return $this;
    }

    public function getDurationFrom(): ?int
    {
        return $this->durationFrom;
    }

    public function setDurationFrom(?int $durationFrom): self
    {
        $this->durationFrom = $durationFrom;

        return $this;
    }

    public function getDurationUntil(): ?int
    {
        return $this->durationUntil;
    }

    public function setDurationUntil(?int $durationUntil): self
    {
        $this->durationUntil = $durationUntil;

        return $this;
    }

    public function getBitrateFrom(): ?int
    {
        return $this->bitrateFrom;
    }

    public function setBitrateFrom(?int $bitrateFrom): self
    {
        $this->bitrateFrom = $bitrateFrom;

        return $this;
    }

    public function getBitrateUntil(): ?int
    {
        return $this->bitrateUntil;
    }

    public function setBitrateUntil(?int $bitrateUntil): self
    {
        $this->bitrateUntil = $bitrateUntil;

        return $this;
    }

    public function getCreatedAtFrom(): ?DateTimeImmutable
    {
        return $this->createdAtFrom;
    }

    public function setCreatedAtFrom(?DateTimeImmutable $createdAtFrom): self
    {
        $this->createdAtFrom = $createdAtFrom;

        return $this;
    }

    public function getCreatedAtUntil(): ?DateTimeImmutable
    {
        return $this->createdAtUntil;
    }

    public function setCreatedAtUntil(?DateTimeImmutable $createdAtUntil): self
    {
        $this->createdAtUntil = $createdAtUntil;

        return $this;
    }

    public function isGeneratedBySystem(): ?bool
    {
        return $this->generatedBySystem;
    }

    public function setGeneratedBySystem(?bool $generatedBySystem): self
    {
        $this->generatedBySystem = $generatedBySystem;

        return $this;
    }

    public function isFromRss(): ?bool
    {
        return $this->fromRss;
    }

    public function setFromRss(?bool $fromRss): self
    {
        $this->fromRss = $fromRss;

        return $this;
    }

    public function getShortestDimensionFrom(): ?int
    {
        return $this->shortestDimensionFrom;
    }

    public function setShortestDimensionFrom(?int $shortestDimensionFrom): self
    {
        $this->shortestDimensionFrom = $shortestDimensionFrom;

        return $this;
    }

    public function getShortestDimensionUntil(): ?int
    {
        return $this->shortestDimensionUntil;
    }

    public function setShortestDimensionUntil(?int $shortestDimensionUntil): self
    {
        $this->shortestDimensionUntil = $shortestDimensionUntil;

        return $this;
    }

    public function getSlotNames(): array
    {
        return $this->slotNames;
    }

    public function setSlotNames(array $slotNames): self
    {
        $this->slotNames = $slotNames;

        return $this;
    }

    public function getDistributedInServices(): array
    {
        return $this->distributedInServices;
    }

    public function setDistributedInServices(array $distributedInServices): self
    {
        $this->distributedInServices = $distributedInServices;

        return $this;
    }

    public function getSlotsCountFrom(): ?int
    {
        return $this->slotsCountFrom;
    }

    public function setSlotsCountFrom(?int $slotsCountFrom): self
    {
        $this->slotsCountFrom = $slotsCountFrom;

        return $this;
    }

    public function getSlotsCountUntil(): ?int
    {
        return $this->slotsCountUntil;
    }

    public function setSlotsCountUntil(?int $slotsCountUntil): self
    {
        $this->slotsCountUntil = $slotsCountUntil;

        return $this;
    }
}
