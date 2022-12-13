<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto;

use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class KeywordAdmSearchDto extends AbstractSearchDto
{
    #[Serialize]
    protected string $text = '';

    #[Serialize]
    protected ?bool $reviewed = null;

    public function getIndexName(): string
    {
        return Keyword::getResourceName();
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

    public function isReviewed(): ?bool
    {
        return $this->reviewed;
    }

    public function setReviewed(?bool $reviewed): self
    {
        $this->reviewed = $reviewed;

        return $this;
    }
}
