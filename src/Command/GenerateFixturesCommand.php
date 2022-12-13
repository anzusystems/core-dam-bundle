<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\DataFixtures\FixturesLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:fixtures:generate',
    description: 'Generates application fixtures.'
)]
final class GenerateFixturesCommand extends Command
{
    public function __construct(
        private readonly FixturesLoader $fixturesLoader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fixturesLoader->load($output);

        return Command::SUCCESS;
    }
}
