<?php

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorCleanPhraseRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ext_system_phrase', fields: ['phrase', 'extSystem'])]
#[UniqueEntity(fields: ['phrase', 'extSystem'])]
final class AuthorCleanPhrase implements
    IdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface
{
    use IdentityIntTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\ManyToOne(targetEntity: ExtSystem::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['collation' => 'utf8mb4_bin'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Serialize]
    private string $phrase;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Assert\When(
        expression: 'this->getMode()->is(remove)',
        constraints: [
            new Assert\Url(message: ValidationException::ERROR_FIELD_URL),
            new Assert\Length(min: 2, max: 255, minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX),
        ],
        values: ['remove' => AuthorCleanPhraseMode::Remove]
    )]
    #[Serialize]
    private string $replacement;

    #[ORM\Column(enumType: AuthorCleanPhraseType::class)]
    #[Serialize]
    private AuthorCleanPhraseType $type;

    #[ORM\Column(enumType: AuthorCleanPhraseMode::class)]
    #[Serialize]
    private AuthorCleanPhraseMode $mode;

    public function __construct()
    {
        $this->setPhrase('');
        $this->setReplacement('');
        $this->setType(AuthorCleanPhraseType::Default);
        $this->setMode(AuthorCleanPhraseMode::Default);
        $this->setExtSystem(new ExtSystem());
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

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function setReplacement(string $replacement): self
    {
        $this->replacement = $replacement;
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
}