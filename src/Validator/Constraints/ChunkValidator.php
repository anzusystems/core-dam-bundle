<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ChunkValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @param ChunkAdmCreateDto $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (false === ($value instanceof ChunkAdmCreateDto)) {
            return;
        }

        $imageChunkConfig = $this->configurationProvider->getSettings()->getImageChunkConfig();

        if (false === ($value->getAssetFile()->getAssetAttributes()->getUploadedSize() === $value->getOffset())) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('offset')
                ->addViolation();
        }

        if (false === ($value->getSize() === $value->getFile()->getSize())) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('size')
                ->addViolation();
        }

        $isLastChunk = $value->getAssetFile()->getAssetAttributes()->getSize() ===
            $value->getOffset() + $value->getSize();

        if ($value->getSize() < $imageChunkConfig->getMinSize() && false === $isLastChunk) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MIN)
                ->atPath('size')
                ->addViolation();
        }

        if ($value->getSize() > $imageChunkConfig->getMaxSize()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath('size')
                ->addViolation();
        }
    }
}
