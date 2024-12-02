<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorType;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Author>
 */
final class AuthorFixtures extends AbstractFixtures
{
    public const string AUTHOR_1 = '690fd785-84b1-4d3b-abdf-b986ed53c317';
    public const string AUTHOR_2 = '19a0dba5-459b-422e-ac8e-a3c1cbd20d36';
    public const string AUTHOR_3 = '7470b436-6e03-4417-9b92-50af90aa09bf';

    public const string AUTHOR_4 = '7470b436-6e03-4427-9b92-50af90aa09bf';
    public const string AUTHOR_5 = '7470b436-6e03-4437-9b92-50af90aa09bf';
    public const string AUTHOR_6 = '7470b436-6e03-4447-9b92-50af90aa09bf';

    public function __construct(
        private readonly AuthorManager $manager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return Author::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Author $author */
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

        $author = (new Author())
            ->setId(self::AUTHOR_1)
            ->setName('Larry Queen')
            ->setExtSystem($cmsExtSystem);
        $author->getFlags()->setReviewed(true);
        yield $author;

        $author = (new Author())
            ->setId(self::AUTHOR_2)
            ->setName('Aarne Ormonde')
            ->setExtSystem($cmsExtSystem);
        $author->getFlags()->setReviewed(true);
        yield $author;

        $author = (new Author())
            ->setId(self::AUTHOR_3)
            ->setName('Malka Raisa')
            ->setExtSystem($cmsExtSystem);
        $author->getFlags()->setReviewed(true);

        yield $author;

        $author5 = (new Author())
            ->setId(self::AUTHOR_5)
            ->setType(AuthorType::Agency)
            ->setName('AgencyA')
            ->setExtSystem($cmsExtSystem);
        $author5->getFlags()->setReviewed(true);

        yield $author5;

        $author6 = (new Author())
            ->setId(self::AUTHOR_6)
            ->setName('AuthorB')
            ->setExtSystem($cmsExtSystem);
        $author6->getFlags()->setReviewed(true);

        yield $author6;

        $childAuthor = (new Author())
            ->setId(self::AUTHOR_4)
            ->setName('AgencyA/AuthorB')
            ->setCurrentAuthors(CollectionHelper::newCollection([$author5, $author6]))
            ->setExtSystem($cmsExtSystem);
        $childAuthor->getFlags()->setReviewed(false);

        yield $childAuthor;
    }
}
