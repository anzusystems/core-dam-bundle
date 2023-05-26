<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ImageCropTag: string implements EnumInterface
{
    use BaseEnumTrait;

    public const LIST = 'list';
    public const TABLE = 'table';
    public const DETAIL = 'detail';
    public const ROI_EXAMPLE = 'roi_example';

    case List = self::LIST;
    case Detail = self::DETAIL;
    case RoiExample = self::ROI_EXAMPLE;
    case Table = self::TABLE;
}
