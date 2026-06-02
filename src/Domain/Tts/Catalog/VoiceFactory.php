<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use JsonException;

/**
 * Deserializes a request body into the concrete {@see Voice} subclass selected by its `discriminator`
 * field — the serializer cannot resolve the abstract root type on its own.
 */
final readonly class VoiceFactory
{
    public function __construct(
        private Serializer $serializer,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function fromJson(string $content): Voice
    {
        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = [];
        }

        $discriminator = is_array($data) ? ($data['discriminator'] ?? VoiceDiscriminator::Default->value) : VoiceDiscriminator::Default->value;
        $class = VoiceDiscriminator::MAP[$discriminator] ?? VoiceDiscriminator::MAP[VoiceDiscriminator::Default->value];

        return $this->serializer->deserialize($content, $class);
    }
}
