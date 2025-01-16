<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits\PublicImageLinksTrait;

class PublicLinksTagCollectionHandler extends LinksTagCollectionHandler
{
    use PublicImageLinksTrait;
}
