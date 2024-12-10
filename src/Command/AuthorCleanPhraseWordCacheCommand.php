<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFacade;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseWordCache;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFacade;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:author-clean-phrase-cache:debug',
    description: 'Debug AuthorCleanPhraseWordCache'
)]
final class AuthorCleanPhraseWordCacheCommand extends Command
{
    public function __construct(
        private readonly AuthorCleanPhraseWordCache $authorCleanPhraseWordCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (AuthorCleanPhraseType::cases() as $type) {
            foreach (AuthorCleanPhraseMode::cases() as $mode) {
                $list = $this->authorCleanPhraseWordCache->getList($type, $mode);
                $output->writeln('<info>'. AuthorCleanPhraseWordCache::getCacheKey($type, $mode) .'</info>');

                foreach ($list as $item) {
                    $output->writeln($item);
                }
            }
        }

        return Command::SUCCESS;
    }
}
