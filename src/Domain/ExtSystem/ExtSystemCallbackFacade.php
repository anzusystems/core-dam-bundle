<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseBuilderInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Throwable;

final class ExtSystemCallbackFacade
{
    private ServiceLocator $extSystemCallbackLocator;

    public function __construct(
        #[AutowireLocator(ExtSystemCallbackInterface::class, indexAttribute: 'key')]
        ServiceLocator $extSystemCallbackLocator,
        private readonly DamLogger $logger,
    ) {
        $this->extSystemCallbackLocator = $extSystemCallbackLocator;
    }

    public function notifyFinishedJobImageCopy(JobImageCopy $jobImageCopy): void
    {
        $this->getCallback($jobImageCopy->getLicence()->getExtSystem()->getSlug())->notifyFinishedJobImageCopy($jobImageCopy);
    }

    private function getCallback(string $slug): ?ExtSystemCallbackInterface
    {
        try {
            return $this->extSystemCallbackLocator->get($slug);
        } catch (Throwable $e) {
            $this->logger->warning('ExtSystemCallback', $e->getMessage());

            return null;
        }
    }
}
