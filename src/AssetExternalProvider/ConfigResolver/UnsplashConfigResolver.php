<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\AssetExternalProvider\ConfigResolver;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\UnsplashAssetExternalProviderConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UnsplashConfigResolver implements ConfigResolverInterface
{
    public function resolve(array $config): UnsplashAssetExternalProviderConfiguration
    {
        $resolver = new OptionsResolver();
        $resolver->setRequired(UnsplashAssetExternalProviderConfiguration::TITLE_KEY);
        $resolver->setRequired(UnsplashAssetExternalProviderConfiguration::PROVIDER_KEY);
        $resolver->setAllowedTypes(UnsplashAssetExternalProviderConfiguration::TITLE_KEY, 'string');
        $resolver->setAllowedTypes(UnsplashAssetExternalProviderConfiguration::PROVIDER_KEY, 'string');

        $resolver->setDefault(
            UnsplashAssetExternalProviderConfiguration::OPTIONS_KEY,
            function (OptionsResolver $resolver) {
                $resolver->setRequired(UnsplashAssetExternalProviderConfiguration::ACCESS_KEY);
                $resolver->setRequired(UnsplashAssetExternalProviderConfiguration::LISTING_LIMIT_KEY);
                $resolver->setDefault(UnsplashAssetExternalProviderConfiguration::LISTING_LIMIT_KEY, AssetExternalProviderConfiguration::DEFAULT_LISTING_LIMIT);
                $resolver->setAllowedTypes(UnsplashAssetExternalProviderConfiguration::ACCESS_KEY, 'string');
                $resolver->setAllowedTypes(UnsplashAssetExternalProviderConfiguration::LISTING_LIMIT_KEY, 'int');
            },
        );

        return UnsplashAssetExternalProviderConfiguration::getFromArrayConfiguration(
            $resolver->resolve($config)
        );
    }
}
