<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ParamConverter;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class CollectionParamConverter implements ParamConverterInterface
{
    use SerializerAwareTrait;

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $data = $this->serializer->deserializeIterable(
            (string) $request->getContent(),
            $configuration->getClass(),
            new ArrayCollection()
        );

        $request->attributes->set($configuration->getName(), $data);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return false === empty($configuration->getClass()) &&
            self::class === $configuration->getConverter();
    }
}
