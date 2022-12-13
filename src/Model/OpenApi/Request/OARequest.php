<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\OpenApi\Request;

use Attribute;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\RequestBody;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final class OARequest extends RequestBody
{
    public function __construct(
        string|array $model,
    ) {
        if (is_iterable($model)) {
            parent::__construct(
                content: new JsonContent(
                    type: 'array',
                    items: new Items(
                        ref: new Model(
                            type: $model[(int) array_key_first($model)]
                        )
                    )
                )
            );
        }

        if (is_string($model)) {
            parent::__construct(
                content: new JsonContent(
                    ref: new Model(
                        type: $model
                    )
                )
            );
        }
    }
}
