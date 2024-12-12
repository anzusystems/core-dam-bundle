<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\ExySystemApiParams;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;
use Doctrine\ORM\Exception\ORMException;

final readonly class AuthorCleanPhraseRepositoryDecorator
{
    public function __construct(
        private AuthorCleanPhraseRepository $authorCleanPhraseRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParams(
        ApiParams $apiParams,
        ExtSystem $extSystem,
    ): ApiResponseList {
        $customFilters = [new CustomExtSystemFilter()];
        $apiParams = ExySystemApiParams::applyCustomFilter($apiParams, $extSystem);

        return $this->authorCleanPhraseRepository->findByApiParams(
            apiParams: $apiParams,
            customFilters: $customFilters,
        );
    }
}
