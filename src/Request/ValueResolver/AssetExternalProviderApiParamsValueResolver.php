<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ValueResolver;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Routing\Exception\InvalidParameterException;

final class AssetExternalProviderApiParamsValueResolver implements ValueResolverInterface
{
    use SerializerAwareTrait;

    private const string ATTRIBUTE_NAME = 'providerName';

    public function __construct(
        private readonly AssetExternalProviderContainer $providerContainer,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (AssetExternalProviderApiParams::class === $argument->getType()) {
            return $this->resolveProviderParams($request);
        }

        return [];
    }

    private function resolveProviderParams(Request $request): iterable
    {
        $providerName = (string) $request->attributes->get(self::ATTRIBUTE_NAME);
        if (false === $this->providerContainer->has($providerName)) {
            throw new InvalidParameterException(sprintf('Missing "%s" attribute on route.', self::ATTRIBUTE_NAME));
        }

        return [AssetExternalProviderApiParams::createFromRequestAndConfig(
            request: $request,
            configuration: $this->providerContainer->get($providerName)->getConfiguration(),
        )];
    }
}
