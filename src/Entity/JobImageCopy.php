<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobImageCopyRepository::class)]
class JobImageCopy extends Job
{
    #[ORM\ManyToOne(targetEntity: AssetLicence::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $licence;

    #[ORM\OneToMany(targetEntity: JobImageCopyItem::class, mappedBy: 'job')]
    private Collection $items;

    public function __construct()
    {
        parent::__construct();
        $this->setLicence(new AssetLicence());
        $this->setItems(new ArrayCollection());
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    /**
     * @return Collection<int, JobImageCopyItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param Collection<int, JobImageCopyItem> $items
     */
    public function setItems(Collection $items): self
    {
        $this->items = $items;

        return $this;
    }
}
