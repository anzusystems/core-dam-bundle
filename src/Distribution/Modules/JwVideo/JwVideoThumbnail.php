<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\HttpClient\JwVideoClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\JwVideoThumbnailPosterMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\JwMediaStatus;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;

final class JwVideoThumbnail extends AbstractDistributionDtoFactory
{
    use MessageBusAwareTrait;

    public function __construct(
        private readonly JwVideoClient $jwVideoClient,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly DamLogger $damLogger,
    ) {
    }

    public function setVideoThumbnail(string $imageUrl, string $videoId, string $distribService): void
    {
        $config = $this->distributionConfigurationProvider->getJwDistributionService($distribService);

        $thumbnail = $this->jwVideoClient->createThumbnail(
            configuration: $config,
            imageUrl: $imageUrl,
            jwId: $videoId
        );

        $this->messageBus->dispatch(new JwVideoThumbnailPosterMessage(
            thumbnailId: $thumbnail->getId(),
            distribService: $distribService
        ));
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    public function makeThumbnailPoster(string $thumbnailId, string $distribService): void
    {
        $config = $this->distributionConfigurationProvider->getJwDistributionService($distribService);

        $thumbnail = $this->jwVideoClient->getThumbnail($config, $thumbnailId);
        if ($thumbnail->getStatus()->is(JwMediaStatus::Ready)) {
            $this->jwVideoClient->setPoster(
                configuration: $config,
                thumbnailId: $thumbnailId
            );

            return;
        }
        if ($thumbnail->getStatus()->is(JwMediaStatus::Failed)) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf(
                    'Jw thumbnail id (%s) failed (%s)',
                    $thumbnailId,
                    $thumbnail->getErrorMessage()
                )
            );

            return;
        }

        throw new RemoteProcessingWaitingException();
    }
}
