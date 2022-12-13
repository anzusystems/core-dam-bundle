<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ParamConverter;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AssetExternalProviderApiParamConverter implements ParamConverterInterface
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly AssetExternalProviderContainer $providerContainer,
    ) {
    }

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $providerName = (string) $request->attributes->get('providerName');
        if (false === $this->providerContainer->has($providerName)) {
            throw new BadRequestHttpException('Missing "providerName" attribute on route.');
        }
        $assetExternalProviderApiParams = AssetExternalProviderApiParams::createFromRequestAndConfig(
            request: $request,
            configuration: $this->providerContainer->get($providerName)->getConfiguration(),
        );
        $request->attributes->set($configuration->getName(), $assetExternalProviderApiParams);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return AssetExternalProviderApiParams::class === $configuration->getClass();
    }
}
