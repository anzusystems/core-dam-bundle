<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Google_Service_YouTube_I18nLanguage;

final class YoutubeLanguageDto
{
    #[Serialize]
    private string $id = '';

    #[Serialize]
    private string $title = '';

    public static function createFromGoogle(Google_Service_YouTube_I18nLanguage $languageItem): self
    {
        return (new self())
            ->setId($languageItem->getId())
            ->setTitle($languageItem->getSnippet()->getName());
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
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
}
