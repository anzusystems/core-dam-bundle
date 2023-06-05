<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Cache\AudioRouteGenerator;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\ORM\NonUniqueResultException;

final class AudioLinksHandler extends AbstractHandler
{
    private const LINKS_TYPE = 'audio';

    public function __construct(
        private readonly ImageLinksHandler $imageLinksHandler,
        private readonly AudioRouteGenerator $audioRouteGenerator,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        if (null === $value) {
            return null;
        }

        if (false === ($value instanceof AudioFile)) {
            throw new SerializerException(sprintf('Value should be instance of (%s)', ImageFile::class));
        }

        $links = [];
        $imageFile = $this->getImagePreview($value);
        if ($imageFile) {
            $links = $this->imageLinksHandler->getImageLinkUrl($imageFile, [ImageLinksHandler::TAG_LIST, ImageLinksHandler::TAG_TABLE]);
        }
        if ($value->getAudioPublicLink()->isPublic()) {
            $links[self::LINKS_TYPE] = $this->serializeImagePublicLink($value);
        }

        return $links;
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    private function getImagePreview(AssetFile $assetFile): ?ImageFile
    {
        $firstEpisode = $assetFile->getAsset()->getEpisodes()->first();

        if ($firstEpisode instanceof PodcastEpisode) {
            return $firstEpisode->getImagePreview()?->getImageFile() ?? $firstEpisode->getPodcast()->getImagePreview()?->getImageFile();
        }

        return null;
    }

    private function serializeImagePublicLink(AudioFile $audioFile): array
    {
        return [
            'type' => self::LINKS_TYPE,
            'url' => $this->audioRouteGenerator->getFullUrl(
                path: $audioFile->getAudioPublicLink()->getPath(),
                extSlug: $audioFile->getExtSystem()->getSlug()
            ),
        ];
    }
}
