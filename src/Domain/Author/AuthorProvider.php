<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;

final readonly class AuthorProvider
{
    public function __construct(
        private AuthorRepository $repository,
        private AuthorManager $authorManager,
        private IndexManager $indexManager,
    ) {
    }

    public function provideByTitle(string $title, ExtSystem $extSystem): ?Author
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

        $author = $this->authorManager->create(
            author: (new Author())
                ->setExtSystem($extSystem)
                ->setName($title),
        );
        // Provider-created authors bypass AuthorFacade, so index them here — otherwise newly created
        // authors (TTS narration, sys asset-file/from-url) exist in DB but never reach Elasticsearch.
        $this->indexManager->index($author);

        return $author;
    }

    public function provideCurrentAuthorToColl(Asset $asset): bool
    {
        $changedCurrentAuthors = false;
        foreach ($asset->getAuthors()->toArray() as $assetAuthor) {
            if ($assetAuthor->getCurrentAuthors()->isEmpty()) {
                continue;
            }

            $changedCurrentAuthors = true;

            foreach ($assetAuthor->getCurrentAuthors() as $currentAuthor) {
                $asset->getAuthors()->add($currentAuthor);
            }

            if (false === $assetAuthor->getFlags()->isReviewed()) {
                $asset->getAuthors()->removeElement($assetAuthor);
            }
        }

        return $changedCurrentAuthors;
    }
}
