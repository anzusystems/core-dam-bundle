<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Logger;

use AnzuSystems\CommonBundle\Document\LogContext;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

final class DamLogger
{
    use SerializerAwareTrait;

    public const string NAMESPACE_ELASTICSEARCH = 'ElasticSearch';
    public const string NAMESPACE_EXIFTOOL = 'Exiftool';
    public const string NAMESPACE_ASSET_CHANGE_STATE = 'AssetChangeState';
    public const string NAMESPACE_ASSET_PROPERTY_REFRESHER = 'AssetPropertyRefresher';
    public const string NAMESPACE_ASSET_FILE_CHANGE_STATE = 'AssetFileChangeState';
    public const string NAMESPACE_DISTRIBUTION = 'Distribution';
    public const string NAMESPACE_ASSET_EXTERNAL_PROVIDER = 'AssetExternalProvider';
    public const string NAMESPACE_PODCAST_RSS_IMPORT = 'PodcastRssImport';
    public const string NAMESPACE_VISP = 'Visp';
    public const string NAMESPACE_ASSET_FILE_PROCESS = 'AssetFileProcess';
    public const string NAMESPACE_ASSET_FILE_DOWNLOAD = 'AssetFileDownload';

    public function __construct(
        private readonly LoggerInterface $journalLogger,
        private readonly LogContextFactory $contextFactory,
    ) {
    }

    /**
     * @throws JsonException
     * @throws SerializerException
     */
    public function error(string $namespace, string $message, array $content = [], array $params = [], ?Throwable $exception = null): void
    {
        $this->journalLogger->error("[{$namespace}] {$message}", $this->createContext($content, $params, $exception));
    }

    /**
     * @throws JsonException
     * @throws SerializerException
     */
    public function warning(string $namespace, string $message = '', array $content = [], array $params = []): void
    {
        $this->journalLogger->warning("[{$namespace}] {$message}", $this->createContext($content, $params));
    }

    /**
     * @throws JsonException
     * @throws SerializerException
     */
    public function info(string $namespace, string $message = '', array $content = [], array $params = []): void
    {
        $this->journalLogger->info("[{$namespace}] {$message}", $this->createContext($content, $params));
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    protected function createContext(array $content = [], array $params = [], ?Throwable $exception = null): array
    {
        $context = $this->contextFactory->buildBaseContext();
        $context->setContent(json_encode($content, JSON_THROW_ON_ERROR));
        $context->setParams($params);

        if ($exception) {
            $this->setExceptionToContext($exception, $context);
        }

        /** @var array $arrayContext */
        $arrayContext = $this->serializer->toArray($context);

        return $arrayContext;
    }

    protected function setExceptionToContext(Throwable $exception, LogContext $context): void
    {
        $exceptionClass = $exception::class;
        $error = $exception->getMessage();

        $contextException = $context->getException();
        $context->setException(
            $contextException ? ($contextException . ' <- ' . $exceptionClass) : $exceptionClass
        );
        $contextError = $context->getError();
        $context->setError(
            $contextError ? ($contextError . ' <- ' . $error) : $error
        );
        $prevException = $exception->getPrevious();
        if ($prevException) {
            $this->setExceptionToContext($prevException, $context);
        }
    }
}
