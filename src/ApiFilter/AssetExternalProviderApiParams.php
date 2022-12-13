<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidParameterException;

final class AssetExternalProviderApiParams
{
    private const LIMIT = 'limit';
    private const OFFSET = 'offset';
    private const TERM = 'term';

    private const DEFAULTS = [
        self::LIMIT => AssetExternalProviderConfiguration::DEFAULT_LISTING_LIMIT,
        self::OFFSET => 0,
        self::TERM => '',
    ];

    #[Serialize]
    private int $limit;

    #[Serialize]
    private int $offset;

    #[Serialize]
    private string $term;

    public function __construct()
    {
        $this->limit = self::DEFAULTS[self::LIMIT];
        $this->offset = self::DEFAULTS[self::OFFSET];
        $this->term = self::DEFAULTS[self::TERM];
    }

    public static function createFromRequestAndConfig(
        Request $request,
        AssetExternalProviderConfiguration $configuration
    ): self {
        $limit = $request->query->getInt(self::LIMIT, $configuration->getListingLimit());
        if (false === ($limit === $configuration->getListingLimit())) {
            throw new InvalidParameterException('limit_value_not_allowed');
        }

        return (new self())
            ->setLimit($limit)
            ->setOffset($request->query->getInt(self::OFFSET, self::DEFAULTS[self::OFFSET]))
            ->setTerm((string) $request->query->get(self::TERM, self::DEFAULTS[self::TERM]))
        ;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(mixed $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(mixed $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): self
    {
        $this->term = $term;

        return $this;
    }
}
