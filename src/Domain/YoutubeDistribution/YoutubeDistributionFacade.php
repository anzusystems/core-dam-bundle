<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeApiClient;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Domain\Distribution\AbstractDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Google\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class YoutubeDistributionFacade extends AbstractDistributionFacade
{
    public function __construct(
        private readonly YoutubeDataStorage $youtubeDataStorage,
        private readonly YoutubeApiClient $youtubeApiClient,
        private readonly YoutubeAuthenticator $youtubeAuthenticator,
        private readonly DistributionBodyBuilder $distributionBodyBuilder,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws ForbiddenOperationException
     */
    public function preparePayload(AssetFile $assetFile, string $distributionService): YoutubeDistribution
    {
        try {
            $config = $this->distributionConfigurationProvider->getYoutubeDistributionService($distributionService);
        } catch (DomainException) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        $distribution = new YoutubeDistribution();
        $this->distributionBodyBuilder->setBaseFields($assetFile, $distributionService, $distribution);
        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );
        $distribution->setLanguage($config->getDefaultLanguage());

        return $distribution;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function getPlaylists(string $serviceId, bool $force = false): ApiResponseList
    {
        if (false === $this->youtubeAuthenticator->isAuthenticated($serviceId)) {
            throw new AccessDeniedException('Youtube is not authenticated');
        }

        if ($force || false === $this->youtubeDataStorage->hasPlaylist($serviceId)) {
            $playlists = $this->youtubeApiClient->getPlaylists($serviceId);
            $this->youtubeDataStorage->storePlaylists($playlists, $serviceId);
        }
        $data = $this->youtubeDataStorage->getPlaylists($serviceId);

        return (new ApiResponseList())
            ->setTotalCount(count($data))
            ->setData($data);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getLanguage(string $serviceId): ApiResponseList
    {
        $config = $this->distributionConfigurationProvider->getYoutubeDistributionService($serviceId);

        if (false === $this->youtubeDataStorage->hasLanguages($config->getRegionCode())) {
            $languages = $this->youtubeApiClient->getLanguages($config->getRegionCode());
            $this->youtubeDataStorage->storeLanguages($languages, $config->getRegionCode());
        }

        $data = $this->youtubeDataStorage->getLanguages($config->getRegionCode());

        return (new ApiResponseList())
            ->setTotalCount(count($data))
            ->setData($data);
    }
}
