<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSuggester implements DataSuggesterInterface
{
    protected const int MAX_IDS_SUGGESTIONS = 10;

    protected ExtSystemConfigurationProvider $extSystemConfigurationProvider;
    protected SuggestionTagParser $suggestionTagParser;
    protected ?ExtSystemAssetTypeExifMetadataConfiguration $configurationCache = null;

    #[Required]
    public function setSuggestionTagParser(SuggestionTagParser $suggestionTagParser): void
    {
        $this->suggestionTagParser = $suggestionTagParser;
    }

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $configurationProvider): void
    {
        $this->extSystemConfigurationProvider = $configurationProvider;
    }

    public function suggest(AssetFile $assetFile, array $metadata): void
    {
        $tags = $this->suggestionTagParser->parse(
            configuration: $this->getConfiguration($assetFile),
            metadata: $metadata
        );

        $this->suggestWithTags($assetFile, $tags);
    }

    public function supports(AssetFile $assetFile): bool
    {
        $authorSettings = $this->getConfiguration($assetFile);

        return false === $assetFile->getAsset()->getAssetFlags()->isDescribed()
            && $authorSettings->isEnabled()
            && false === empty($authorSettings->getAutocompleteFromMetadataTags());
    }

    protected function suggestWithTags(AssetFile $assetFile, array $tags): void
    {
        $originAsset = $assetFile->getAsset();
        $suggestions = [];
        $iteration = 0;
        foreach ($tags as $tag) {
            $ids = [];
            /** @psalm-suppress TypeDoesNotContainNull */
            if ($iteration < self::MAX_IDS_SUGGESTIONS) {
                $ids = $this->suggestIdsByTag($tag, $originAsset);
            }
            $suggestions[$tag] = $ids;
            ++$iteration;
        }

        $this->storeSuggestionsOnAsset($originAsset, $suggestions);
    }

    abstract protected function storeSuggestionsOnAsset(Asset $asset, array $suggestions): void;

    /**
     * @return list<string>
     */
    abstract protected function suggestIdsByTag(string $name, Asset $asset): array;

    abstract protected function getConfiguration(AssetFile $assetFile): ExtSystemAssetTypeExifMetadataConfiguration;
}
