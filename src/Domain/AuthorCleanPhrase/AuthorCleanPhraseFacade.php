<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseCache;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Exception\AuthorCleanPhraseException;

final readonly class AuthorCleanPhraseFacade
{
    public function __construct(
        private Validator $validator,
        private AuthorCleanPhraseManager $manager,
        private AuthorCleanPhraseCache $authorCleanPhraseCache,
    ) {
    }

    /**
     * Process new authorCleanPhrase creation.
     *
     * @throws ValidationException
     * @throws AuthorCleanPhraseException
     */
    public function create(AuthorCleanPhrase $bannedPhrase): AuthorCleanPhrase
    {
        $this->validator->validate($bannedPhrase);
        $this->manager->create($bannedPhrase);
        $this->authorCleanPhraseCache->refreshCacheByPhrase($bannedPhrase);

        return $bannedPhrase;
    }

    /**
     * Process updating of authorCleanPhrase.
     *
     * @throws ValidationException
     * @throws AuthorCleanPhraseException
     */
    public function update(AuthorCleanPhrase $bannedPhrase, AuthorCleanPhrase $newBannedPhrase): AuthorCleanPhrase
    {
        $this->validator->validate($newBannedPhrase, $bannedPhrase);
        $this->manager->update($bannedPhrase, $newBannedPhrase);
        $this->authorCleanPhraseCache->refreshCacheByPhrase($bannedPhrase);

        return $bannedPhrase;
    }

    /**
     * Process deletion.
     * @throws AuthorCleanPhraseException
     */
    public function delete(AuthorCleanPhrase $bannedPhrase): bool
    {
        $deleted = $this->manager->delete($bannedPhrase);
        $this->authorCleanPhraseCache->refreshCacheByPhrase($bannedPhrase);

        return $deleted;
    }
}
