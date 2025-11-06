<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AuthorAdmSearchDto extends AbstractSearchDto
{
    #[Serialize]
    protected string $text = '';

    #[Serialize]
    protected ?bool $canBeCurrentAuthor = null;

    #[Serialize]
    protected string $identifier = '';

    #[Serialize]
    protected ?bool $reviewed = null;

    #[Serialize]
    protected ?string $type = null;

    public function isCanBeCurrentAuthor(): ?bool
    {
        return $this->canBeCurrentAuthor;
    }

    public function setCanBeCurrentAuthor(?bool $canBeCurrentAuthor): self
    {
        $this->canBeCurrentAuthor = $canBeCurrentAuthor;

        return $this;
    }

    public function getIndexName(): string
    {
        return Author::getResourceName();
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

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function isReviewed(): ?bool
    {
        return $this->reviewed;
    }

    public function setReviewed(?bool $reviewed): self
    {
        $this->reviewed = $reviewed;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
