<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetAuthorDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AuthorDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorCurrentOptimizeRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Throwable;

/**
 * Marks the root authors with the given names as sources of an ext system. The whole file is checked first, so a wrong
 * name changes nothing. After the commit of each author a JobAuthorCurrentOptimize moves its image links to sources.
 */
final class AuthorMarkSourceFacade
{
    use IndexManagerAwareTrait;

    public const string JOB_CREATED = 'created';
    public const string JOB_WAITING = 'waiting';
    public const string JOB_WOULD_CREATE = 'would create';

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly AuthorDBALRepository $authorDBALRepository,
        private readonly AssetAuthorDBALRepository $assetAuthorDBALRepository,
        private readonly AuthorManager $authorManager,
        private readonly JobFacade $jobFacade,
        private readonly JobAuthorCurrentOptimizeRepository $jobAuthorCurrentOptimizeRepository,
    ) {
    }

    /**
     * @return list<Author>
     *
     * @throws InvalidArgumentException with every wrong name, one per line
     */
    public function readSources(ExtSystem $extSystem, string $path): array
    {
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if (false === $lines) {
            throw new InvalidArgumentException(sprintf('File %s cannot be read', $path));
        }

        $names = array_values(array_unique(array_filter(array_map(trim(...), $lines), static fn (string $name): bool => App::EMPTY_STRING !== $name)));
        if (empty($names)) {
            throw new InvalidArgumentException(sprintf('File %s has no name', $path));
        }

        $errors = [];
        $authors = [];
        foreach ($names as $name) {
            $rootIds = $this->authorDBALRepository->findRootIdsByName((int) $extSystem->getId(), $name);
            $author = 1 === count($rootIds) ? $this->authorRepository->find($rootIds[App::ZERO]) : null;
            if (null === $author) {
                $errors[] = sprintf('%s: %d roots, expected 1', $name, count($rootIds));

                continue;
            }
            $authors[] = $author;
        }
        if (false === empty($errors)) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return $authors;
    }

    /**
     * @param list<Author> $authors
     *
     * @return list<array{name: string, id: string, links: int, job: string}>
     */
    public function markSources(array $authors, bool $dryRun): array
    {
        $rows = [];
        foreach ($authors as $author) {
            $authorId = (string) $author->getId();
            $links = $this->assetAuthorDBALRepository->countByAuthor($authorId);
            $waiting = $this->jobAuthorCurrentOptimizeRepository->existsWaitingForAuthor($authorId);
            $job = $waiting ? self::JOB_WAITING : ($dryRun ? self::JOB_WOULD_CREATE : self::JOB_CREATED);
            if (false === $dryRun) {
                $this->markSource($author);
                if (false === $waiting) {
                    $this->jobFacade->create((new JobAuthorCurrentOptimize())->setAuthorId($authorId));
                }
            }
            $rows[] = ['name' => $author->getName(), 'id' => $authorId, 'links' => $links, 'job' => $job];
        }

        return $rows;
    }

    private function markSource(Author $author): void
    {
        $this->authorManager->beginTransaction();

        try {
            $this->authorManager->markSource($author);
            $this->authorManager->commit();
        } catch (Throwable $exception) {
            $this->authorManager->rollback();

            throw $exception;
        }

        $this->indexManager->indexBulk([$author]);
    }
}
