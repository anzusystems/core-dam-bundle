<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileGroupUsageReader;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\ImageFileUsage;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\DependencyInjection\ServiceLocator;

trait AssetFileGroupUsageReaderAnsweringTrait
{
    /**
     * @param array<string, ImageFileUsage> $usage
     */
    private function groupUsageReaderAnswering(array $usage): AssetFileGroupUsageReader
    {
        $callback = self::createStub(ExtSystemCallbackInterface::class);
        $callback->method('resolveImageFileUsage')->willReturn($usage);

        $facade = new ExtSystemCallbackFacade(
            new ServiceLocator([
                self::CMS_SLUG => static fn (): ExtSystemCallbackInterface => $callback,
            ]),
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        );

        return new AssetFileGroupUsageReader($this->assetFileRepository, $facade);
    }
}
