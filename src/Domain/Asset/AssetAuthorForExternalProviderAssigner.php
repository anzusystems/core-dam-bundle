<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Traits\ResourceLockerAwareTrait;
use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;

final class AssetAuthorForExternalProviderAssigner
{
    use ResourceLockerAwareTrait;

    public function __construct(
        private readonly ExtSystemConfigurationProvider $configurationProvider,
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    /**
     * @throws AnzuException
     */
    public function assign(Asset $asset, string $providerName): Asset
    {
        $extSystem = $asset->getExtSystem();
        $config = $this->configurationProvider
            ->getExtSystemConfiguration($extSystem->getSlug())
            ->getAssetExternalProviders()
            ->get($providerName);
        if (null === $config) {
            throw new AnzuException(sprintf(
                'Configuration for asset external provider (%s) and ext system (%s) not found!',
                $extSystem->getSlug(),
                $providerName
            ));
        }

        $authorId = $config->getImportAuthorId();
        if (empty($authorId)) {
            return $asset;
        }

        $author = $this->authorRepository->find($authorId);
        if ($author instanceof Author) {
            $asset->getAuthors()->add($author);
        }

        return $asset;
    }
}
