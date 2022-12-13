<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;

class DistributionServiceMockOptionsConfiguration
{
    public const SLEEP_KEY = 'sleep';
    public const FAIL_REASON = 'fail_reason';
    public const EXT_ID_KEY = 'ext_id';
    public const DISTRIBUTION_DATA_KEY = 'distribution_data';

    public function __construct(
        private readonly int $sleep,
        private readonly DistributionFailReason $failReason,
        private readonly string $extId,
        private readonly array $distributionData,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): static
    {
        return new static(
            $config[self::SLEEP_KEY] ?? 0,
            isset($config[self::FAIL_REASON])
                ? DistributionFailReason::from($config[self::FAIL_REASON])
                : DistributionFailReason::None
            ,
            $config[self::EXT_ID_KEY] ?? '',
            $config[self::DISTRIBUTION_DATA_KEY] ?? [],
        );
    }

    public function getSleep(): int
    {
        return $this->sleep;
    }

    public function getFailReason(): DistributionFailReason
    {
        return $this->failReason;
    }

    public function getExtId(): string
    {
        return $this->extId;
    }

    public function getDistributionData(): array
    {
        return $this->distributionData;
    }
}
