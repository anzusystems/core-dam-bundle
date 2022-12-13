<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Util;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class OutputUtil
{
    private OutputInterface $output;

    public function __construct()
    {
        $this->output = new NullOutput();
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function createProgressBar(int $max = 0): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }

    public function writeln(string $message, int $options = 0): void
    {
        $this->output->writeln($message, $options);
    }

    public function error(string $message, int $options = 0): void
    {
        $this->output->writeln("<error>{$message}</error>", $options);
    }

    public function info(string $message, int $options = 0): void
    {
        $this->output->writeln("<info>{$message}</info>", $options);
    }
}
