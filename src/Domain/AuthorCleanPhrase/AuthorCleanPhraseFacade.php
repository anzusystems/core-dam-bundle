<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;

final readonly class AuthorCleanPhraseFacade
{
    public function __construct(
        private Validator $validator,
        private AuthorCleanPhraseManager $manager,
    ) {
    }

    /**
     * Process new authorCleanPhrase creation.
     *
     * @throws ValidationException
     */
    public function create(AuthorCleanPhrase $bannedPhrase): AuthorCleanPhrase
    {
        $this->validator->validate($bannedPhrase);
        $this->manager->create($bannedPhrase);
//        if ($bannedPhrase->getType()->is(BannedPhraseType::Word)) {
//            $this->bannedPhraseWordProvider->warmupCache();
//        }

        return $bannedPhrase;
    }

    /**
     * Process updating of authorCleanPhrase.
     *
     * @throws ValidationException
     */
    public function update(AuthorCleanPhrase $bannedPhrase, AuthorCleanPhrase $newBannedPhrase): AuthorCleanPhrase
    {
        $this->validator->validate($newBannedPhrase, $bannedPhrase);
        $this->manager->update($bannedPhrase, $newBannedPhrase);
//        if ($bannedPhrase->getType()->is(BannedPhraseType::Word)) {
//            $this->bannedPhraseWordProvider->warmupCache();
//        }

        return $bannedPhrase;
    }

    /**
     * Process deletion.
     */
    public function delete(AuthorCleanPhrase $bannedPhrase): bool
    {
        $deleted = $this->manager->delete($bannedPhrase);
//        if ($deleted && $bannedPhrase->getType()->is(BannedPhraseType::Word)) {
//            $this->bannedPhraseWordProvider->warmupCache();
//        }

        return $deleted;
    }

    public function clean(string $postTexts): string
    {
        return '';
//        return $postTexts->setContent(
//            $this->cleaner->getClean($postTexts->getContentRaw())
//        );
    }
}
