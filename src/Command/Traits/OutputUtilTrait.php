<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command\Traits;

use AnzuSystems\CoreDamBundle\Util\OutputUtil;
use Symfony\Contracts\Service\Attribute\Required;

trait OutputUtilTrait
{
    protected OutputUtil $outputUtil;

    #[Required]
    public function setOutputUtil(OutputUtil $outputUtil): void
    {
        $this->outputUtil = $outputUtil;
    }

    protected function writeln(string $message, int $options = 0): void
    {
        $this->outputUtil->writeln($message, $options);
    }
}
