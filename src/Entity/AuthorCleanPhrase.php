<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AuthorCleanPhraseFlags;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorCleanPhraseRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ext_system_phrase', fields: ['phrase', 'extSystem'])]
#[BaseAppAssert\UniqueEntity(fields: ['phrase', 'extSystem'])]
final class AuthorCleanPhrase implements
    IdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    ExtSystemInterface,
    PositionableInterface
{
    use IdentityIntTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;
    use PositionTrait;

    #[ORM\ManyToOne(targetEntity: ExtSystem::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['collation' => 'utf8mb4_bin'])]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Serialize]
    private string $phrase;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[Assert\When(
        expression: 'this.getMode().is(replace)',
        constraints: [
            new Assert\NotNull(message: ValidationException::ERROR_FIELD_EMPTY),
        ],
        values: ['replace' => AuthorCleanPhraseMode::Replace]
    )]
    #[AppAssert\EqualExtSystem]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?Author $authorReplacement = null;

    #[ORM\Column(enumType: AuthorCleanPhraseType::class)]
    #[Serialize]
    private AuthorCleanPhraseType $type;

    #[ORM\Column(enumType: AuthorCleanPhraseMode::class)]
    #[Assert\Expression(
        expression: 'this.getType().isNot(regex) or this.getMode().is(remove)',
        message: ValidationException::ERROR_FIELD_INVALID,
        values: ['regex' => AuthorCleanPhraseType::Regex, 'remove' => AuthorCleanPhraseMode::Remove]
    )]
    #[Serialize]
    private AuthorCleanPhraseMode $mode;

    #[ORM\Embedded]
    #[Serialize]
    private AuthorCleanPhraseFlags $flags;

    public function __construct()
    {
        $this->setPhrase('');
        $this->setType(AuthorCleanPhraseType::Default);
        $this->setMode(AuthorCleanPhraseMode::Default);
        $this->setExtSystem(new ExtSystem());
        $this->setFlags(new AuthorCleanPhraseFlags());
    }

    public function getPhrase(): string
    {
        return $this->phrase;
    }

    public function setPhrase(string $phrase): self
    {
        $this->phrase = $phrase;

        return $this;
    }

    public function getType(): AuthorCleanPhraseType
    {
        return $this->type;
    }

    public function setType(AuthorCleanPhraseType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getMode(): AuthorCleanPhraseMode
    {
        return $this->mode;
    }

    public function setMode(AuthorCleanPhraseMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getAuthorReplacement(): ?Author
    {
        return $this->authorReplacement;
    }

    public function setAuthorReplacement(?Author $authorReplacement): self
    {
        $this->authorReplacement = $authorReplacement;

        return $this;
    }

    public function getFlags(): AuthorCleanPhraseFlags
    {
        return $this->flags;
    }

    public function setFlags(AuthorCleanPhraseFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }
}
