<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class TtsProviderContainer
{
    public function __construct(
        #[AutowireLocator(TtsProviderInterface::class, indexAttribute: 'key')]
        private ServiceLocator $locator,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    public function forDiscriminator(VoiceDiscriminator $discriminator): TtsProviderInterface
    {
        if (false === $this->locator->has($discriminator->value)) {
            throw new TtsProviderException(
                sprintf('TTS provider "%s" is not registered in the container.', $discriminator->value)
            );
        }

        return $this->locator->get($discriminator->value);
    }
}
