<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache;

use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Throwable;

final class AuthorCleanPhraseCache
{
    private ServiceLocator $cacheBuilderLocator;

    public function __construct(
        private readonly CacheItemPoolInterface $coreDamBundleAuthorCleanPhraseCache,
        #[AutowireLocator(AuthorCleanPhraseBuilderInterface::class, indexAttribute: 'key')]
        ServiceLocator $cacheBuilderLocator,
    ) {
        $this->cacheBuilderLocator = $cacheBuilderLocator;
    }

    /**
     * @throws AuthorCleanPhraseException
     * @return array<int, non-empty-string>
     */
    public function getList(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode, ExtSystem $extSystem): array
    {
        $item = $this->coreDamBundleAuthorCleanPhraseCache->getItem(AbstractAuthorCleanPhraseBuilder::getCacheKey($type, $mode, $extSystem));

        if ($item->isHit()) {
            return $item->get();
        }

        return $this->buildCache($type, $mode, $extSystem, $item);
    }

    public function cleanCache(): void
    {
        $this->coreDamBundleAuthorCleanPhraseCache->clear();
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    public function refreshCacheByPhrase(AuthorCleanPhrase $authorCleanPhrase): void
    {
        $this->refreshCache(
            $authorCleanPhrase->getType(),
            $authorCleanPhrase->getMode(),
            $authorCleanPhrase->getExtSystem()
        );
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    public function refreshCache(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode, ExtSystem $extSystem): void
    {
        $item = $this->coreDamBundleAuthorCleanPhraseCache->getItem(AbstractAuthorCleanPhraseBuilder::getCacheKey($type, $mode, $extSystem));

        $this->buildCache($type, $mode, $extSystem, $item);
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    private function buildCache(AuthorCleanPhraseType $type, AuthorCleanPhraseMode $mode, ExtSystem $extSystem, CacheItemInterface $item): array
    {
        $regexes = $this->getCacheBuilder($type)->buildCache($mode, $extSystem);
        $item->set($regexes);
        $this->coreDamBundleAuthorCleanPhraseCache->save($item);

        return $regexes;
    }

    /**
     * @throws AuthorCleanPhraseException
     */
    private function getCacheBuilder(AuthorCleanPhraseType $type): AuthorCleanPhraseBuilderInterface
    {
        try {
            return $this->cacheBuilderLocator->get($type->value);
        } catch (Throwable $e) {
            throw new AuthorCleanPhraseException(
                message: AuthorCleanPhraseException::ERROR_CACHE_BUILDER_MISSING,
                detail: 'Cache builder for type ' . $type->value . ' is missing.',
                previous: $e
            );
        }
    }
}
