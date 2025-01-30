<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class JwDistributionAdmUpdateDto extends AbstractDistributionUpdateDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 2_048, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $directSourceUrl = '';

    public function getDirectSourceUrl(): string
    {
        return $this->directSourceUrl;
    }

    public function setDirectSourceUrl(string $directSourceUrl): self
    {
        $this->directSourceUrl = $directSourceUrl;
        return $this;
    }
}
