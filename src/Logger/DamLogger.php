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
    public const NAMESPACE_ASSET_FILE_CHANGE_STATE = 'AssetFileChangeState';
    public const NAMESPACE_DISTRIBUTION = 'Distribution';
    public const NAMESPACE_ASSET_EXTERNAL_PROVIDER = 'AssetExternalProvider';
    public const NAMESPACE_PODCAST_RSS_IMPORT = 'PodcastRssImport';
    public const NAMESPACE_VISP = 'Visp';

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

        $this->appLogger->error("[{$namespace}] {$message}", $this->serializer->toArray($context));
    }

    /**
     * @throws SerializerException
     */
    public function info(string $namespace, string $message = '', string $content = '', array $params = []): void
    {
        $context = $this->contextFactory->buildBaseContext();
        $context->setContent($content);
        $context->setParams($params);

        $this->appLogger->info("[{$namespace}] {$message}", $this->serializer->toArray($context));
    }
}
