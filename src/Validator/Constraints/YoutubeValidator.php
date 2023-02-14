<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution\YoutubeAbstractDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\PlaylistDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeLanguageDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class YoutubeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly YoutubeAbstractDistributionFacade $playlistFacade,
        private readonly YoutubeAuthenticator $youtubeAuthenticator,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof YoutubeDistribution)) {
            throw new UnexpectedTypeException($constraint, YoutubeDistribution::class);
        }

        if (false === $this->youtubeAuthenticator->isAuthenticated($value->getDistributionService())) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('distributionService')
                ->addViolation();

            return;
        }

        if (
            false === empty($value->getPlaylist()) &&
            false === $this->hasPlaylist($value->getDistributionService(), $value->getPlaylist())
        ) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('playlist')
                ->addViolation();
        }

        if (
            false === empty($value->getLanguage()) &&
            false === $this->hasLanguage($value->getDistributionService(), $value->getLanguage())
        ) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('language')
                ->addViolation();
        }
    }

    /**
     * @throws SerializerException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function hasPlaylist(string $distributionService, string $playlistId): bool
    {
        $playlistResponse = $this->playlistFacade->getPlaylists($distributionService);
        /** @var PlaylistDto $playlistItem */
        foreach ($playlistResponse->getData() as $playlistItem) {
            if ($playlistItem->getId() === $playlistId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function hasLanguage(string $distributionService, string $languageId): bool
    {
        $languageResponse = $this->playlistFacade->getLanguage($distributionService);

        /** @var YoutubeLanguageDto $langItem */
        foreach ($languageResponse->getData() as $langItem) {
            if ($langItem->getId() === $languageId) {
                return true;
            }
        }

        return false;
    }
}
