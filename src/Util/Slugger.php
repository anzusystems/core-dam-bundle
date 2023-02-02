<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Util;

use Symfony\Component\String\AbstractUnicodeString;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

final class Slugger implements SluggerInterface
{
    private readonly SluggerInterface $slugger;

    public function __construct(
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function slug(string $string, string $separator = '-', string $locale = null): AbstractUnicodeString
    {
        return $this->slugger->slug(
            $string,
            $separator,
            $locale,
        )->lower();
    }
}
