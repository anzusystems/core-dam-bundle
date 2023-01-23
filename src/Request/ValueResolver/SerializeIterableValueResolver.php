<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ValueResolver;

use AnzuSystems\CoreDamBundle\Model\Attributes\SerializeIterableParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Routing\Exception\InvalidArgumentException;

final class SerializeIterableValueResolver implements ValueResolverInterface
{
    public function __construct(
        private Serializer $serializer,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(SerializeIterableParam::class)[0] ?? null;
        if ($attribute instanceof SerializeIterableParam) {
            return $this->resolveSerializeIterable($attribute, $request);
        }

        return [];
    }

    /**
     * @throws SerializerException
     */
    private function resolveSerializeIterable(SerializeIterableParam $attribute, Request $request): iterable
    {
        /** @var ArrayCollection $items */
        $items = $this->serializer->deserializeIterable($request->getContent(), $attribute->type, new ArrayCollection());
        if (is_int($attribute->maxItems) && $items->count() > $attribute->maxItems) {
            throw new InvalidArgumentException('max_items_reached');
        }

        return [$items];
    }
}
