<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ParamConverter;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class ArrayStringParamConverter implements ParamConverterInterface
{
    public const ITEMS_LIMIT = 'items_limit';

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $ids = array_map(
            fn ($id): string => trim($id),
            explode(',', (string) $request->attributes->get($configuration->getName()))
        );
        $idCount = count($ids);
        $itemsLimit = $configuration->getOptions()[self::ITEMS_LIMIT] ?? null;

        if (is_numeric($itemsLimit) && $idCount > $itemsLimit) {
            throw new InvalidArgumentException('invalid_array_string_count');
        }

        $request->attributes->set($configuration->getName(), $ids);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return self::class === $configuration->getConverter();
    }
}
