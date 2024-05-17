<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordManager;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Keyword>
 */
final class KeywordFixtures extends AbstractFixtures
{
    public const string KEYWORD_1 = '85ab9277-44e1-4462-95f0-abe6367802f7';
    public const string KEYWORD_2 = '566f9d87-1b30-4ce7-825f-2739cb1556bc';
    public const string KEYWORD_3 = '161d0174-b760-41de-94f5-693d16dde94a';

    public function __construct(
        private readonly KeywordManager $manager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return Keyword::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Keyword $keyword */
        foreach ($progressBar->iterate($this->getData()) as $keyword) {
            $keyword = $this->manager->create($keyword);
            $this->addToRegistry($keyword, (string) $keyword->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var ExtSystem $cmsExtSystem */
        $cmsExtSystem = $this->entityManager->find(
            ExtSystem::class,
            1
        );

        $keyword = (new Keyword())
            ->setId(self::KEYWORD_1)
            ->setName('Politics')
            ->setExtSystem($cmsExtSystem);
        $keyword->getFlags()->setReviewed(true);
        yield $keyword;

        $keyword = (new Keyword())
            ->setId(self::KEYWORD_2)
            ->setName('Podcast')
            ->setExtSystem($cmsExtSystem);
        $keyword->getFlags()->setReviewed(true);
        yield $keyword;

        $keyword = (new Keyword())
            ->setId(self::KEYWORD_3)
            ->setName('News')
            ->setExtSystem($cmsExtSystem);
        $keyword->getFlags()->setReviewed(true);
        yield $keyword;
    }
}
