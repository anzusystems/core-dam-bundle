<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LicenceCollectionHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

final class DistributionAdmSearchDto extends AbstractSearchDto implements LicenceCollectionInterface
{
    #[Serialize]
    protected string $text = '';

    #[Serialize]
    #[Assert\Choice(choices: [
        YoutubeDistribution::DISCRIMINATOR,
        JwDistribution::DISCRIMINATOR,
    ], message: ValidationException::ERROR_FIELD_INVALID)]
    protected ?string $service = null;

    #[Serialize]
    protected ?string $serviceSlug = null;

    #[Serialize]
    #[Assert\Length(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected ?string $extId = null;

    #[Serialize]
    #[Assert\Length(max: 20, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected ?DistributionProcessStatus $status = null;

    #[Serialize(handler: LicenceCollectionHandler::class, type: AssetLicence::class)]
    #[Assert\Count(
        min: 1,
        max: 20,
        minMessage: ValidationException::ERROR_FIELD_RANGE_MIN,
        maxMessage: ValidationException::ERROR_FIELD_RANGE_MAX
    )]
    protected Collection $licences;

    public function __construct()
    {
        $this->setLicences(new ArrayCollection());
    }

    public function getIndexName(): string
    {
        return Distribution::getResourceName();
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): void
    {
        $this->service = $service;
    }

    public function getServiceSlug(): ?string
    {
        return $this->serviceSlug;
    }

    public function setServiceSlug(?string $serviceSlug): void
    {
        $this->serviceSlug = $serviceSlug;
    }

    public function getStatus(): ?DistributionProcessStatus
    {
        return $this->status;
    }

    public function setStatus(?DistributionProcessStatus $status): void
    {
        $this->status = $status;
    }

    public function getExtId(): ?string
    {
        return $this->extId;
    }

    public function setExtId(?string $extId): void
    {
        $this->extId = $extId;
    }

    /**
     * @return Collection<int, AssetLicence>
     */
    public function getLicences(): Collection
    {
        return $this->licences;
    }

    /**
     * @param Collection<int, AssetLicence> $licences
     */
    public function setLicences(Collection $licences): self
    {
        $this->licences = $licences;

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
}
