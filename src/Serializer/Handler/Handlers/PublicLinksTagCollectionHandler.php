<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits\PublicImageLinksTrait;

final class PublicLinksTagCollectionHandler extends LinksTagCollectionHandler
{
    use PublicImageLinksTrait;
}
