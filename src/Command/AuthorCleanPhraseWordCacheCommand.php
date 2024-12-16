<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AbstractAuthorCleanPhraseBuilder;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseCache;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'anzu-dam:author-clean-phrase-cache:debug',
    description: 'Debug AuthorCleanPhraseWordCache'
)]
final class AuthorCleanPhraseWordCacheCommand extends Command
{
    private const string ARG_EXT_SYSTEM_SLUG = 'ext_system_slug';

    public function __construct(
        private readonly AuthorCleanPhraseCache $authorCleanPhraseWordCache,
        private readonly ExtSystemRepository $extSystemRepository,
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
        $extSystem = $this->extSystemRepository->findOneBySlug($extSystemSlug);

        if (null === $extSystem) {
            $output->writeln('<error>Ext system not found</error>');

            return Command::FAILURE;
        }

        foreach (AuthorCleanPhraseType::cases() as $type) {
            foreach (AuthorCleanPhraseMode::cases() as $mode) {
                try {
                    $this->authorCleanPhraseWordCache->refreshCache($type, $mode, $extSystem);
                    $list = $this->authorCleanPhraseWordCache->getList($type, $mode, $extSystem);
                    $output->writeln('<info>' . AbstractAuthorCleanPhraseBuilder::getCacheKey($type, $mode, $extSystem) . '</info>');

                    foreach ($list as $item) {
                        $output->writeln($item);
                    }
                } catch (Throwable $e) {
                    if ($e instanceof AuthorCleanPhraseException && AuthorCleanPhraseException::ERROR_INVALID_MODE_AND_COMBINATION === $e->getMessage()) {
                        continue;
                    }
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
        }

        return Command::SUCCESS;
    }
}
