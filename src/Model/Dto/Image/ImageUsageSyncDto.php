<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Declarative statement of what one usage scope holds: "in this scope, this holder uses exactly these
 * photos". Anything the scope holds and this request does not list is released, so a caller that forgets
 * to send a removal cannot leak an exclusivity claim.
 */
final class ImageUsageSyncDto
{
    /**
     * Higher than the first use batch on purpose: that one is a batch the caller may split at will, while a
     * usage declaration is all-or-nothing for its scope — sending half of a gallery would release the other
     * half. The cap only has to stay above any single scope.
     */
    public const int MAX_ITEMS = 1_000;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $scopeResourceName = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $scopeResourceId = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $holderResourceName = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $holderResourceId = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Count(max: self::MAX_ITEMS, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\All([new Assert\Uuid(message: ValidationException::ERROR_FIELD_INVALID)])]
    private Collection $damIds;

    public function __construct()
    {
        $this->setDamIds(new ArrayCollection());
    }

    /**
     * An empty holder means the photos stay in the scope with nobody holding them; half a holder would
     * make the pair the picker compares against ambiguous.
     */
    #[Assert\Callback]
    public function validateHolderPair(ExecutionContextInterface $context): void
    {
        $nameEmpty = App::EMPTY_STRING === $this->holderResourceName;
        if ($nameEmpty === (App::EMPTY_STRING === $this->holderResourceId)) {
            return;
        }

        $context->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
            ->atPath($nameEmpty ? 'holderResourceName' : 'holderResourceId')
            ->addViolation()
        ;
    }

    public function getScopeResourceName(): string
    {
        return $this->scopeResourceName;
    }

    public function setScopeResourceName(string $scopeResourceName): self
    {
        $this->scopeResourceName = $scopeResourceName;

        return $this;
    }

    public function getScopeResourceId(): string
    {
        return $this->scopeResourceId;
    }

    public function setScopeResourceId(string $scopeResourceId): self
    {
        $this->scopeResourceId = $scopeResourceId;

        return $this;
    }

    public function getHolderResourceName(): string
    {
        return $this->holderResourceName;
    }

    public function setHolderResourceName(string $holderResourceName): self
    {
        $this->holderResourceName = $holderResourceName;

        return $this;
    }

    public function getHolderResourceId(): string
    {
        return $this->holderResourceId;
    }

    public function setHolderResourceId(string $holderResourceId): self
    {
        $this->holderResourceId = $holderResourceId;

        return $this;
    }

    /**
     * @return Collection<array-key, string>
     */
    public function getDamIds(): Collection
    {
        return $this->damIds;
    }

    public function setDamIds(Collection $damIds): self
    {
        $this->damIds = $damIds;

        return $this;
    }
}
