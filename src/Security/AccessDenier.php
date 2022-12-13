<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDenier
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @throws AccessDeniedException
     */
    public function denyUnlessGranted(
        string $attribute,
        mixed $subject = null,
        string $message = 'Access Denied.'
    ): void {
        if (false === $this->configurationProvider->getSettings()->isAclCheckEnabled()) {
            return;
        }

        if ($this->authorizationChecker->isGranted($attribute, $subject)) {
            return;
        }

        $exception = new AccessDeniedException($message);
        $exception->setAttributes($attribute);
        $exception->setSubject($subject);

        throw $exception;
    }
}
