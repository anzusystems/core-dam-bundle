<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\YoutubeDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\PlaylistCollectionDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\PlaylistDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeLanguageDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeVideoDto;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Exception;
use Google_Client;
use Google_Http_MediaFileUpload;
use Google_Service_Exception;
use Google_Service_YouTube;
use Google_Service_YouTube_I18nLanguage;
use Google_Service_YouTube_PlaylistItem;
use Google_Service_YouTube_PlaylistItemSnippet;
use Google_Service_YouTube_ResourceId;
use Google_Service_YouTube_Video;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\File;

final class YoutubeApiClient
{
    public const MAX_PAGE = 5;
    public const MAX_RESULTS = 50;

    private const QUOTA_EXCEEDED_REASON = 'quotaExceeded';
    private const VIDEO_RESOURCE_TYPE = 'youtube#video';
    private const CHUNK_SIZE = 5 * 1_024 * 1_024;

    public function __construct(
        private readonly YoutubeVideoFactory $videoFactory,
        private readonly GoogleClientProvider $clientProvider,
        private readonly YoutubeAuthenticator $authenticator,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @return array<int, YoutubeLanguageDto>
     */
    public function getLanguages(string $regionCode): array
    {
        $client = $this->clientProvider->getKeyClient();
        $youtubeService = new Google_Service_YouTube($client);

        $response = $youtubeService->i18nLanguages->listI18nLanguages(
            'snippet',
            [
                'hl' => $regionCode,
            ]
        );

        return array_map(
            fn (Google_Service_YouTube_I18nLanguage $language): YoutubeLanguageDto => YoutubeLanguageDto::createFromGoogle($language),
            $response->getItems()
        );
    }

    /**
     * @return array<int, PlaylistDto>
     *
     * @throws Exception
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    public function getPlaylists(string $distributionService): array
    {
        $client = $this->clientProvider->getClient($distributionService);
        $client->setAccessToken($this->authenticator->getAccessToken($distributionService)->getAccessToken());

        $playlistCollection = $this->bulkFetchPlaylists($client);
        $playlistDtoList = $playlistCollection->getPlaylists();

        $i = 0;
        while ($playlistCollection->getNextPageToken()) {
            $playlistCollection = $this->bulkFetchPlaylists($client, $playlistCollection->getNextPageToken());
            $playlistDtoList = array_merge($playlistDtoList, $playlistCollection->getPlaylists());

            if (self::MAX_PAGE === ++$i) {
                break;
            }
        }

        return $playlistDtoList;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function setPlaylist(
        string $distributionService,
        string $videoId,
        string $playlistId,
    ): void {
        $client = $this->clientProvider->getClient($distributionService);
        $client->setAccessToken($this->authenticator->getAccessToken($distributionService)->getAccessToken());

        $resourceId = new Google_Service_YouTube_ResourceId();
        $resourceId->setVideoId($videoId);
        $resourceId->setKind(self::VIDEO_RESOURCE_TYPE);

        $snippet = new Google_Service_YouTube_PlaylistItemSnippet();
        $snippet->setResourceId($resourceId);
        $snippet->setPlaylistId($playlistId);

        $playlistItem = new Google_Service_YouTube_PlaylistItem();
        $playlistItem->setSnippet($snippet);

        try {
            $youtubeService = new Google_Service_YouTube($client);
            $response = $youtubeService->playlistItems->insert('snippet', $playlistItem);
        } catch (Google_Service_Exception $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('Youtube playlist update failed failed (%s) for ytId (%s)', $exception->getMessage(), $videoId)
            );
        }
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function distribute(
        AssetFile $assetFile,
        YoutubeDistribution $distribution,
        YoutubeDistributionServiceConfiguration $configuration,
        File $file,
    ): ?YoutubeVideoDto {
        $video = $this->videoFactory->createVideo($assetFile, $distribution, $configuration);

        $client = $this->clientProvider->getClient($distribution->getDistributionService());
        $client->setAccessToken($this->authenticator->getAccessToken($distribution->getDistributionService())->getAccessToken());
        $client->setDefer(true);

        $youtubeService = new Google_Service_YouTube($client);

        try {
            $insertRequest = $youtubeService->videos->insert(
                part: 'status,snippet',
                postBody: $video,
                optParams: [
                    'notifySubscribers' => $distribution->getFlags()->isNotifySubscribers(),
                ]
            );
            $media = new Google_Http_MediaFileUpload(
                client: $client,
                request: $insertRequest,
                mimeType: $assetFile->getAssetAttributes()->getMimeType(),
                data: null,
                resumable: true,
                chunkSize: self::CHUNK_SIZE
            );
            $status = false;
            $media->setFileSize($file->getSize());

            $handle = fopen($file->getRealPath(), 'rb');

            while (!$status && !feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                $status = $media->nextChunk($chunk);

                if ($status instanceof Google_Service_YouTube_Video) {
                    return $this->videoFactory->createYoutubeVideoDto($status);
                }
            }

            fclose($handle);
            $client->setDefer(false);

            return null;
        } catch (Google_Service_Exception $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf('Youtube distribute failed (%s)', $exception->getMessage())
            );

            if (self::QUOTA_EXCEEDED_REASON === $this->getExceptionReason($exception)) {
                throw new DistributionFailedException(DistributionFailReason::QuotaReached);
            }

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function getVideo(YoutubeDistributionServiceConfiguration $configuration, string $id): ?YoutubeVideoDto
    {
        $client = $this->clientProvider->getClient($configuration->getServiceId());
        $client->setAccessToken($this->authenticator->getAccessToken($configuration->getServiceId())->getAccessToken());

        $youtubeService = new Google_Service_YouTube($client);

        $res = $youtubeService->videos->listVideos('snippet, status', [
            'id' => $id,
        ]);

        /** @var Google_Service_YouTube_Video $video */
        $video = $res->getItems()[0] ?? null;

        if (null === $video) {
            return null;
        }

        return $this->videoFactory->createYoutubeVideoDto($video);
    }

    private function getExceptionReason(Google_Service_Exception $exception): string
    {
        return $exception->getErrors()[0]['reason'] ?? '';
    }

    private function bulkFetchPlaylists(Google_Client $client, ?string $nextPageToken = null): PlaylistCollectionDto
    {
        $parameters = [
            'mine' => 'true',
            'maxResults' => self::MAX_RESULTS,
        ];
        if ($nextPageToken) {
            $parameters['pageToken'] = $nextPageToken;
        }

        $youtubeService = new Google_Service_YouTube($client);
        $channelsResponse = $youtubeService->playlists->listPlaylists('snippet', $parameters);

        return PlaylistCollectionDto::createFromGoogle($channelsResponse);
    }
}
