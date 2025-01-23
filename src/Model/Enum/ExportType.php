<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExportTypeEnableInterface;

enum ExportType: string implements EnumInterface
{
    use BaseEnumTrait;

    case Web = 'web';
    case Mobile = 'mobile';

    public const ExportType Default = self::Mobile;

    public function isEnabled(ExportTypeEnableInterface $entity): bool
    {
        return match($this) {
            self::Web => $entity->isWebPublicExportEnabled(),
            self::Mobile => $entity->isMobilePublicExportEnabled(),
        };
    }
}
