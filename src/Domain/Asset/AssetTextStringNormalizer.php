<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Helper\HtmlHelper;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\HtmlNormalizerConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;

final class AssetTextStringNormalizer
{
    public function normalizeAll(string $value, array $normalizers): string
    {
        foreach ($normalizers as $normalizer) {
            $value = $this->normalize($value, $normalizer);
        }

        return $value;
    }

    public function normalize(string $value, object $normalizer): string
    {
        if (HtmlNormalizerConfiguration::class === $normalizer::class) {
            return HtmlHelper::htmlToText($value, $normalizer);
        }
        if (StringNormalizerConfiguration::class === $normalizer::class) {
            return StringHelper::normalize($value, $normalizer);
        }

        return $value;
    }
}
