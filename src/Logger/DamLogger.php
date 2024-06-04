<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Logger;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerInterface;
use Throwable;

final class DamLogger
{
    use SerializerAwareTrait;

    public const NAMESPACE_ELASTICSEARCH = 'ElasticSearch';
    public const NAMESPACE_EXIFTOOL = 'Exiftool';
    public const NAMESPACE_ASSET_CHANGE_STATE = 'AssetChangeState';
    public const NAMESPACE_ASSET_PROPERTY_REFRESHER = 'AssetPropertyRefresher';
    public const NAMESPACE_ASSET_FILE_CHANGE_STATE = 'AssetFileChangeState';
    public const NAMESPACE_DISTRIBUTION = 'Distribution';
    public const NAMESPACE_ASSET_EXTERNAL_PROVIDER = 'AssetExternalProvider';
    public const NAMESPACE_PODCAST_RSS_IMPORT = 'PodcastRssImport';
    public const NAMESPACE_VISP = 'Visp';
    public const NAMESPACE_ASSET_FILE_PROCESS = 'AssetFileProcess';
    public const NAMESPACE_ASSET_FILE_DOWNLOAD = 'AssetFileDownload';

    public function __construct(
        private readonly LoggerInterface $appLogger,
        private readonly LogContextFactory $contextFactory,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function error(string $namespace, string $message, ?Throwable $exception = null): void
    {
        $context = $this->contextFactory->buildBaseContext();

        if ($exception) {
            $context->setException($exception::class);
            $context->setError($exception->getMessage());
        }

        /** @var array $arrayContext */
        $arrayContext = $this->serializer->toArray($context);
        $this->appLogger->error("[{$namespace}] {$message}", $arrayContext);
    }

    /**
     * @throws SerializerException
     */
    public function info(string $namespace, string $message = '', string $content = '', array $params = []): void
    {
        $context = $this->contextFactory->buildBaseContext();
        $context->setContent($content);
        $context->setParams($params);

        /** @var array $arrayContext */
        $arrayContext = $this->serializer->toArray($context);
        $this->appLogger->info("[{$namespace}] {$message}", $arrayContext);
    }

    /**
     * @throws SerializerException
     */
    public function warning(string $namespace, string $message = '', string $content = '', array $params = []): void
    {
        $context = $this->contextFactory->buildBaseContext();
        $context->setContent($content);
        $context->setParams($params);

        /** @var array $arrayContext */
        $arrayContext = $this->serializer->toArray($context);
        $this->appLogger->warning("[{$namespace}] {$message}", $arrayContext);
    }
}
