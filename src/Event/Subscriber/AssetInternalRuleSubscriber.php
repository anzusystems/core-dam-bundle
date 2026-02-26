<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileInternalRuleEvaluator;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Event\AssetChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AssetInternalRuleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AssetFileInternalRuleEvaluator $evaluator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetChangedEvent::class => 'onAssetChanged',
        ];
    }

    public function onAssetChanged(AssetChangedEvent $event): void
    {
        foreach ($event->getAffectedAssets() as $asset) {
            $this->processAsset($asset);
        }
    }

    private function processAsset(Asset $asset): void
    {
        foreach ($asset->getSlots() as $slot) {
            /** @var AssetSlot $slot */
            $this->evaluator->evaluateAndApply($asset, $slot->getAssetFile());
        }
    }
}
