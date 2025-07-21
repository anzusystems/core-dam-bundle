<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits\PublicImageLinksTrait;
use AnzuSystems\SerializerBundle\Helper\SerializerHelper;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use OpenApi\Annotations\Property;
use Symfony\Component\TypeInfo\TypeIdentifier;

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
                'type' => TypeIdentifier::ARRAY->value,
                'items' => [
                    'type' => TypeIdentifier::OBJECT->value,
                    'properties' => [
                        'type' => new Property([
                            'property' => 'type',
                            'type' => TypeIdentifier::STRING->value,
                        ]),
                        'url' => new Property([
                            'property' => 'url',
                            'type' => TypeIdentifier::STRING->value,
                        ]),
                        'title' => new Property([
                            'property' => 'title',
                            'type' => TypeIdentifier::STRING->value,
                        ]),
                        'requestedWidth' => new Property([
                            'property' => 'requestedWidth',
                            'type' => SerializerHelper::getOaFriendlyType(TypeIdentifier::INT->value),
                        ]),
                        'requestedHeight' => new Property([
                            'property' => 'requestedHeight',
                            'type' => SerializerHelper::getOaFriendlyType(TypeIdentifier::INT->value),
                        ]),
                    ],
                ],
            ],
        ];
    }
}
