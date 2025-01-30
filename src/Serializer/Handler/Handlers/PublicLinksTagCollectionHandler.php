<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits\PublicImageLinksTrait;
use AnzuSystems\SerializerBundle\Helper\SerializerHelper;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use OpenApi\Annotations\Property;
use Symfony\Component\PropertyInfo\Type;

class PublicLinksTagCollectionHandler extends LinksTagCollectionHandler
{
    use PublicImageLinksTrait;

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return is_a($metadata->type, ImageFile::class, true) || is_a($metadata->type, AudioFile::class, true);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);

        return [
            ...$description,
            ...[
                'type' => Type::BUILTIN_TYPE_ARRAY,
                'items' => [
                    'type' => Type::BUILTIN_TYPE_OBJECT,
                    'properties' => [
                        'type' => new Property([
                            'property' => 'type',
                            'type' => Type::BUILTIN_TYPE_STRING,
                        ]),
                        'url' => new Property([
                            'property' => 'url',
                            'type' => Type::BUILTIN_TYPE_STRING,
                        ]),
                        'title' => new Property([
                            'property' => 'title',
                            'type' => Type::BUILTIN_TYPE_STRING,
                        ]),
                        'requestedWidth' => new Property([
                            'property' => 'requestedWidth',
                            'type' => SerializerHelper::getOaFriendlyType(Type::BUILTIN_TYPE_INT),
                        ]),
                        'requestedHeight' => new Property([
                            'property' => 'requestedHeight',
                            'type' => SerializerHelper::getOaFriendlyType(Type::BUILTIN_TYPE_INT),
                        ]),
                    ],
                ],
            ],
        ];
    }
}
