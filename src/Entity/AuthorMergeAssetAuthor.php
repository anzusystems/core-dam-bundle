<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Asset links of a merged variant before the job moved them to the main author.
 * Rows are copied by AssetAuthorDBALRepository::copyToMergeSnapshot().
 */
#[ORM\Entity]
#[ORM\Index(name: 'IDX_run_id', fields: ['runId'])]
#[ORM\Index(name: 'IDX_asset_id', fields: ['assetId'])]
class AuthorMergeAssetAuthor implements IdentifiableInterface
{
    use IdentityIntTrait;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $runId = '';

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $assetId = '';

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $authorId = '';

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $mainAuthorId = '';

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getMainAuthorId(): string
    {
        return $this->mainAuthorId;
    }
}
