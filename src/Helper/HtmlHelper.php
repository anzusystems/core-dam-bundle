<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\HtmlNormalizerConfiguration;
use Html2Text\Html2Text;

final class HtmlHelper
{
    public static function htmlToText(string $html, HtmlNormalizerConfiguration $config = new HtmlNormalizerConfiguration()): string
    {
        $html2Text = new Html2Text($html, $config->getHtmlToTextConfig());

        return $html2Text->getText();
    }
}
