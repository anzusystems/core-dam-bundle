<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest} listing to one asset licence
 * ({@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest::$assetLicence} FK relation).
 */
final class TtsNarrationRequestLicenceFilter implements CustomFilterInterface
{
    public const string LICENCE = 'licence';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::LICENCE === $field) {
            $dqb->andWhere('IDENTITY(t.assetLicence) = :licenceId')
                ->setParameter('licenceId', $value);
        }

        return $dqb;
    }
}
