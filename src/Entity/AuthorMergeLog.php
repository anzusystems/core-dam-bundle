<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorMergeDecision;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One merged pair: the variant got the main author as its current author. Values are the state before the merge;
 * ids are plain columns, so the log outlives the authors.
 */
#[ORM\Entity]
#[ORM\Index(name: 'IDX_run_id', fields: ['runId'])]
#[ORM\Index(name: 'IDX_variant_id', fields: ['variantId'])]
class AuthorMergeLog implements IdentifiableInterface
{
    use IdentityIntTrait;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $runId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $extSystemId;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $groupName;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $variantId;

    #[ORM\Column(type: Types::STRING, length: Author::NAME_MAX_LENGTH)]
    private string $variantName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $variantIdentifier;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $variantReviewed;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $variantCanBeCurrentAuthor;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $mainAuthorId;

    #[ORM\Column(type: Types::STRING, length: Author::NAME_MAX_LENGTH)]
    private string $mainAuthorName;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $mainAuthorReviewed;

    #[ORM\Column(enumType: AuthorMergeDecision::class)]
    private AuthorMergeDecision $decision;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $runId, string $groupName, Author $variant, Author $mainAuthor, AuthorMergeDecision $decision)
    {
        $this->runId = $runId;
        $this->extSystemId = (int) $variant->getExtSystem()->getId();
        $this->groupName = $groupName;
        $this->variantId = (string) $variant->getId();
        $this->variantName = $variant->getName();
        $this->variantIdentifier = $variant->getIdentifier();
        $this->variantReviewed = $variant->getFlags()->isReviewed();
        $this->variantCanBeCurrentAuthor = $variant->getFlags()->isCanBeCurrentAuthor();
        $this->mainAuthorId = (string) $mainAuthor->getId();
        $this->mainAuthorName = $mainAuthor->getName();
        $this->mainAuthorReviewed = $mainAuthor->getFlags()->isReviewed();
        $this->decision = $decision;
        $this->createdAt = App::getAppDate();
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getExtSystemId(): int
    {
        return $this->extSystemId;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getVariantName(): string
    {
        return $this->variantName;
    }

    public function getVariantIdentifier(): string
    {
        return $this->variantIdentifier;
    }

    public function isVariantReviewed(): bool
    {
        return $this->variantReviewed;
    }

    public function isVariantCanBeCurrentAuthor(): bool
    {
        return $this->variantCanBeCurrentAuthor;
    }

    public function getMainAuthorId(): string
    {
        return $this->mainAuthorId;
    }

    public function getMainAuthorName(): string
    {
        return $this->mainAuthorName;
    }

    public function isMainAuthorReviewed(): bool
    {
        return $this->mainAuthorReviewed;
    }

    public function getDecision(): AuthorMergeDecision
    {
        return $this->decision;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
