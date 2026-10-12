<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

final class VideoFileChangeStateMessage extends AbstractAssetFileMessage
{
    /**
     * @var list<string>
     */
    private array $distributionServices = [];

    /**
     * @param list<string> $distributionServices
     */
    public function __construct(AssetFile $asset, array $distributionServices = [])
    {
        parent::__construct($asset);
        $this->distributionServices = $distributionServices;
    }

    /**
     * @return list<string>
     */
    public function getDistributionServices(): array
    {
        return $this->distributionServices;
    }
}
