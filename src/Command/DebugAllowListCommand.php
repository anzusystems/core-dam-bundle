<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:allow-list:debug',
    description: 'Synchronize ext system by base configuration.'
)]
final class DebugAllowListCommand extends Command
{
    private const ARG_EXT_SYSTEM_SLUG = 'ext_system_slug';

    use OutputUtilTrait;

    public function __construct(
        private readonly array $domainAllowMap,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument(
            name: self::ARG_EXT_SYSTEM_SLUG,
            mode: InputArgument::REQUIRED,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table  = (new Table($output))
            ->setHeaders(['key', 'crop_allow_list', 'ext_system_slugs', 'domain']);

        /**
         * @var string $key
         * @var array{
         *     crop_allow_list: string,
         *     domain: string,
         *     ext_system_slugs: array<int, string>
         * } $domainAllowMap
         */
        foreach ($this->domainAllowMap as $key => $domainAllowMap) {
            $table->addRow([
                $key, $domainAllowMap['crop_allow_list'], implode(', ', $domainAllowMap['ext_system_slugs']),  $domainAllowMap['domain']
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
