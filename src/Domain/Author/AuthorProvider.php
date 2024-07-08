<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;

final readonly class AuthorProvider
{
    public function __construct(
        private AuthorRepository $repository,
        private AuthorManager $authorManager,
    ) {
    }

    public function provideAuthor(string $title, ExtSystem $extSystem): ?Author
    {
        $title = StringHelper::parseString(input: $title, length: Author::NAME_MAX_LENGTH);
        if (empty($title)) {
            return null;
        }

        $author = $this->repository->findOneBy([
            'name' => $title,
            'extSystem' => $extSystem,
        ]);

        if ($author instanceof Author) {
            return $author;
        }

        return $this->authorManager->create(
            author: (new Author())
                ->setExtSystem($extSystem)
                ->setName($title),
        );
    }
}
