<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter;

final class HtmlNormalizerConfiguration
{
    public const TYPE = 'html';
    public const WORDS_WRAP_KEY = 'words_wrap';

    private ?int $wordsWrap = null;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setWordsWrap($config[self::WORDS_WRAP_KEY] ?? null)
        ;
    }

    public function getWordsWrap(): ?int
    {
        return $this->wordsWrap;
    }

    public function setWordsWrap(?int $wordsWrap): self
    {
        $this->wordsWrap = $wordsWrap;

        return $this;
    }

    public function getHtmlToTextConfig(): array
    {
        $config = [];
        if (false === (null === $this->wordsWrap)) {
            $config['width'] = $this->wordsWrap;
        }

        return $config;
    }
}
