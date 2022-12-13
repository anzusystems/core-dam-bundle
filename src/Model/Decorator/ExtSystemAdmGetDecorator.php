<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAdmGetDecorator
{
    private array $assetExternalProviders;
    private ExtSystemAssetTypeAdmGetDecorator $audio;
    private ExtSystemAssetTypeAdmGetDecorator $video;
    private ExtSystemImageTypeAdmGetDecorator $image;
    private ExtSystemAssetTypeAdmGetDecorator $document;

    public static function getInstance(ExtSystemConfiguration $configuration): self
    {
        return (new self())
            ->setAudio(ExtSystemAssetTypeAdmGetDecorator::getInstance($configuration->getAudio()))
            ->setVideo(ExtSystemAssetTypeAdmGetDecorator::getInstance($configuration->getVideo()))
            ->setImage(
                ExtSystemImageTypeAdmGetDecorator::getInstance($configuration->getImage())
                    ->setRoiWidth($configuration->getImage()->getRoiWidth())
                    ->setRoiHeight($configuration->getImage()->getRoiHeight())
            )
            ->setDocument(ExtSystemAssetTypeAdmGetDecorator::getInstance($configuration->getDocument()))
            ->setAssetExternalProviders(
                array_map(
                    static fn (ExtSystemAssetExternalProviderConfiguration $config) => ExtSystemAssetExternalProviderAdmGetDecorator::getInstance($config),
                    $configuration->getAssetExternalProviders()
                )
            );
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getAssetExternalProviders(): array
    {
        return $this->assetExternalProviders;
    }

    public function setAssetExternalProviders(array $assetExternalProviders): self
    {
        $this->assetExternalProviders = $assetExternalProviders;

        return $this;
    }

    #[Serialize]
    public function getAudio(): ExtSystemAssetTypeAdmGetDecorator
    {
        return $this->audio;
    }

    public function setAudio(ExtSystemAssetTypeAdmGetDecorator $audio): self
    {
        $this->audio = $audio;

        return $this;
    }

    #[Serialize]
    public function getVideo(): ExtSystemAssetTypeAdmGetDecorator
    {
        return $this->video;
    }

    public function setVideo(ExtSystemAssetTypeAdmGetDecorator $video): self
    {
        $this->video = $video;

        return $this;
    }

    #[Serialize]
    public function getImage(): ExtSystemImageTypeAdmGetDecorator
    {
        return $this->image;
    }

    public function setImage(ExtSystemImageTypeAdmGetDecorator $image): self
    {
        $this->image = $image;

        return $this;
    }

    #[Serialize]
    public function getDocument(): ExtSystemAssetTypeAdmGetDecorator
    {
        return $this->document;
    }

    public function setDocument(ExtSystemAssetTypeAdmGetDecorator $document): self
    {
        $this->document = $document;

        return $this;
    }
}
