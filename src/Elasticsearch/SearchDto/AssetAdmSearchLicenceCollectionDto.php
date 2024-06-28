<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LicenceCollectionHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetAdmSearchLicenceCollectionDto extends AssetAdmSearchDto implements LicenceCollectionInterface
{
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
}
