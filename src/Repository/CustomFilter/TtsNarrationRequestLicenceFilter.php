<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest} listing to one asset licence
 * ({@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest::$assetLicence} FK relation).
 */
final class TtsNarrationRequestLicenceFilter implements CustomFilterInterface
{
    public const string LICENCE = 'licence';

    /**
     * Sets this filter's value on the given api params (keeps the wiring out of controllers).
     */
    public static function applyTo(ApiParams $apiParams, AssetLicence $licence): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][self::LICENCE] = (string) $licence->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::LICENCE === $field) {
            $dqb->andWhere('IDENTITY(t.assetLicence) = :licenceId')
                ->setParameter('licenceId', $value);
        }

        return $dqb;
    }
}
