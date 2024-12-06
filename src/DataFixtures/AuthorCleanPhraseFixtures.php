<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseManager;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<AuthorCleanPhrase>
 */
final class AuthorCleanPhraseFixtures extends AbstractFixtures
{
    public function __construct(
        private readonly AuthorCleanPhraseManager $manager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AuthorCleanPhrase::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AuthorCleanPhrase $author */
        foreach ($progressBar->iterate($this->getData()) as $author) {
            $author = $this->manager->create($author);
            $this->addToRegistry($author, (string) $author->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var ExtSystem $cmsExtSystem */
        $cmsExtSystem = $this->entityManager->find(
            ExtSystem::class,
            1
        );

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase('/')
            ->setMode(AuthorCleanPhraseMode::Split)
            ->setType(AuthorCleanPhraseType::Word)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase(',')
            ->setMode(AuthorCleanPhraseMode::Split)
            ->setType(AuthorCleanPhraseType::Word)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase(';')
            ->setMode(AuthorCleanPhraseMode::Split)
            ->setType(AuthorCleanPhraseType::Word)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase('/^[\+]?[\d\s-]{1,20}$/')
            ->setMode(AuthorCleanPhraseMode::Remove)
            ->setType(AuthorCleanPhraseType::Regex)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase('AgencyA')
            ->setReplacement('AgencyA')
            ->setMode(AuthorCleanPhraseMode::Replace)
            ->setType(AuthorCleanPhraseType::Word)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase('AgencyA -')
            ->setReplacement('AgencyA')
            ->setMode(AuthorCleanPhraseMode::Replace)
            ->setType(AuthorCleanPhraseType::Word)
        ;

        yield (new AuthorCleanPhrase())
            ->setExtSystem($cmsExtSystem)
            ->setPhrase('AgencyA-')
            ->setReplacement('AgencyA')
            ->setMode(AuthorCleanPhraseMode::Replace)
            ->setType(AuthorCleanPhraseType::Word)
        ;
    }
}
