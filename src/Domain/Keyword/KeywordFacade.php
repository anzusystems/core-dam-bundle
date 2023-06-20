<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Keyword;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Throwable;

final class KeywordFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly KeywordManager $keywordManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(Keyword $keyword): Keyword
    {
        $this->validator->validate($keyword);

        try {
            $this->keywordManager->beginTransaction();
            $this->keywordManager->create($keyword);
            $this->indexManager->index($keyword);
            $this->keywordManager->commit();
        } catch (Throwable $exception) {
            $this->keywordManager->rollback();

            throw new RuntimeException('keyword_create_failed', 0, $exception);
        }

        return $keyword;
    }

    /**
     * Process updating of keyword.
     *
     * @throws ValidationException
     */
    public function update(Keyword $keyword, Keyword $newKeyword): Keyword
    {
        $this->validator->validate($newKeyword, $keyword);

        try {
            $this->keywordManager->beginTransaction();
            $this->keywordManager->update($keyword, $newKeyword);
            $this->indexManager->index($keyword);
            $this->keywordManager->commit();
        } catch (Throwable $exception) {
            $this->keywordManager->rollback();

            throw new RuntimeException('keyword_update_failed', 0, $exception);
        }

        return $keyword;
    }

    /**
     * Process deletion.
     */
    public function delete(Keyword $keyword): bool
    {
        try {
            $deletedId = (string) $keyword->getId();
            $this->keywordManager->beginTransaction();
            $this->keywordManager->delete($keyword);
            $this->indexManager->delete($keyword, $deletedId);
            $this->keywordManager->commit();
        } catch (Throwable $exception) {
            $this->keywordManager->rollback();

            throw new RuntimeException('keyword_delete_failed', 0, $exception);
        }

        return true;
    }
}
