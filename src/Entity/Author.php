<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AuthorFlags;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorType;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\UniqueConstraint(fields: ['name', 'identifier', 'extSystem'])]
#[BaseAppAssert\UniqueEntity(fields: ['name', 'identifier', 'extSystem'], errorAtPath: ['identifier'])]
class Author implements UuidIdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, ExtSystemIndexableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    public const int NAME_MAX_LENGTH = 255;

    #[ORM\Column(type: Types::STRING, length: self::NAME_MAX_LENGTH)]
    #[Serialize]
    #[Assert\Length(
        min: 2,
        max: self::NAME_MAX_LENGTH,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $name;

    /**
     * If asset uses author and has defined currentAuthors, this relation should be replaced
     */
    #[ORM\ManyToMany(targetEntity: Author::class, inversedBy: 'childAuthors')]
    private Collection $currentAuthors;

    /**
     * Inverse side of currentAuthors
     */
    #[ORM\ManyToMany(targetEntity: Author::class, mappedBy: 'currentAuthors')]
    private Collection $childAuthors;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $identifier;

    #[ORM\ManyToOne]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Embedded]
    #[Serialize]
    private AuthorFlags $flags;

    #[ORM\Column(enumType: AuthorType::class)]
    #[Serialize]
    private AuthorType $type;

    public function __construct()
    {
        $this->setName('');
        $this->setExtSystem(new ExtSystem());
        $this->setIdentifier('');
        $this->setFlags(new AuthorFlags());
        $this->setType(AuthorType::Default);
        $this->setCurrentAuthors(new ArrayCollection());
        $this->setChildAuthors(new ArrayCollection());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getFlags(): AuthorFlags
    {
        return $this->flags;
    }

    public function setFlags(AuthorFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getType(): AuthorType
    {
        return $this->type;
    }

    public function setType(AuthorType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, Author>
     */
    public function getCurrentAuthors(): Collection
    {
        return $this->currentAuthors;
    }

    /**
     * @param Collection<int, Author> $currentAuthors
     */
    public function setCurrentAuthors(Collection $currentAuthors): self
    {
        $this->currentAuthors = $currentAuthors;
        return $this;
    }

    /**
     * @return Collection<int, Author>
     */
    public function getChildAuthors(): Collection
    {
        return $this->childAuthors;
    }

    public function addChildAuthor(Author $author): self
    {
        if (false === $this->childAuthors->contains($author)) {
            $this->childAuthors->add($author);
        }

        return $this;
    }

    public function addCurrentAuthor(Author $author): self
    {
        if (false === $this->currentAuthors->contains($author)) {
            $this->childAuthors->add($author);
        }

        return $this;
    }

    /**
     * @param Collection<int, Author> $childAuthors
     */
    public function setChildAuthors(Collection $childAuthors): self
    {
        $this->childAuthors = $childAuthors;
        return $this;
    }

    public function removeChildAuthor(Author $author): self
    {
        if ($this->childAuthors->contains($author)) {
            $this->childAuthors->removeElement($author);
        }

        return $this;
    }

    public function removeCurrentAuthor(Author $author): self
    {
        if ($this->currentAuthors->contains($author)) {
            $this->currentAuthors->removeElement($author);
        }

        return $this;
    }

    public static function getIndexName(): string
    {
        return self::getResourceName();
    }
}
