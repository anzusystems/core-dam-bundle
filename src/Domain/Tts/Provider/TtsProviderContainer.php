<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class TtsProviderContainer
{
    public const string TAG = 'app.tts.provider';

    public function __construct(
        #[AutowireLocator(TtsProviderInterface::class, indexAttribute: 'key')]
        private ServiceLocator $locator,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    public function forProvider(TtsProvider $provider): TtsProviderInterface
    {
        if (false === $this->locator->has($provider->value)) {
            throw new TtsProviderException(
                sprintf('TTS provider "%s" is not registered in the container.', $provider->value)
            );
        }

        return $this->locator->get($provider->value);
    }
}
