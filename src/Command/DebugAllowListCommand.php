<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Model\Configuration\CacheConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CropAllowListConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:allow-list:debug',
    description: 'Shows crops and domains for specific ext system.'
)]
final class DebugAllowListCommand extends Command
{
    private const string ARG_EXT_SYSTEM_SLUG = 'ext_system_slug';

    public function __construct(
        private readonly array $domainAllowMap,
        private readonly array $domains,
        private readonly array $domainAllowList,
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
        $extSystemSlug = (string) $input->getArgument(self::ARG_EXT_SYSTEM_SLUG);
        $table = (new Table($output))
            ->setHeaders(['domain', 'crop_allow_list', 'width', 'height', 'title', 'tags']);

        /** @var array<string, CacheConfiguration> $domainsToDebug * */
        $domainsToDebug = [];

        /**
         * @var array{
         *     crop_allow_list: string,
         *     domain: string,
         *     ext_system_slugs: array<int, string>
         * } $domainAllowMap
         */
        foreach ($this->domainAllowMap as $domainAllowMap) {
            if (false === in_array($extSystemSlug, $domainAllowMap['ext_system_slugs'], true)) {
                continue;
            }

            $domainConfig = $this->getDomain($domainAllowMap['domain']);
            $domainsToDebug[$domainAllowMap['crop_allow_list']] = $domainConfig;
        }

        foreach ($domainsToDebug as $allowList => $domain) {
            if (false === isset($this->domainAllowList[$allowList])) {
                continue;
            }

            $cropConfig = CropAllowListConfiguration::getFromArrayConfiguration($this->domainAllowList[$allowList]);

            foreach ($cropConfig->getCrops() as $crop) {
                $table->addRow(
                    [
                        $domain->getDomain(),
                        $allowList,
                        $crop['width'],
                        $crop['height'],
                        $crop['title'],
                        implode(', ', $crop['tags']),
                    ]
                );
            }
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function getDomain(string $name): CacheConfiguration
    {
        if (isset($this->domains[$name])) {
            return CacheConfiguration::getFromArrayConfiguration($this->domains[$name]);
        }

        return new CacheConfiguration();
    }
}
