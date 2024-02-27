<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\Distribution as DistributionEntity;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionRequirementStrategy;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class DistributionValidator extends ConstraintValidator
{
    private const string BLOCKED_BY_NOT_SUPPORTED = 'blocked_by_not_supported';
    private const string BLOCKED_BY_INVALID_ASSET_FILE = 'blocked_by_invalid_asset_file';
    private const string BLOCKED_BY_STRATEGY = 'blocked_by_strategy';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly DistributionRepository $distributionRepository,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        // validate if distribution entity is supported by distribution service (jw, custom, yt)
        if (false === ($value instanceof DistributionEntity)) {
            throw new UnexpectedTypeException($constraint, DistributionEntity::class);
        }

        $assetFile = $this->assetFileRepository->find($value->getAssetFileId());

        if (null === $assetFile) {
            throw new NotFoundHttpException("Not found asset file {$value->getAssetFileId()}");
        }

        $this->validateUniqueness($value);
        $this->validateAssetStatus($assetFile);

        $config = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $assetFile->getExtSystem()->getSlug(),
            $assetFile->getAssetType()
        )->getDistribution();

        $this->validateDistributionService($config, $value);
        $this->validateBlockedBy($assetFile, $config, $value);
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function validateUniqueness(Distribution $distribution): void
    {
        $oldDistribution = $this->distributionRepository->findByAssetFileAndDistributionService(
            $distribution->getAssetFileId(),
            $distribution->getDistributionService()
        );

        if (null === $oldDistribution || $oldDistribution->getId() === $distribution->getId()) {
            return;
        }

        $this->context->buildViolation(ValidationException::ERROR_FIELD_UNIQUE)
            ->atPath('distributionService')
            ->addViolation();
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function validateAssetStatus(AssetFile $assetFile): void
    {
        if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return;
        }

        $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath('assetFileId')
            ->addViolation();
    }

    private function validateDistributionService(
        ExtSystemAssetTypeDistributionConfiguration $configuration,
        Distribution $distribution
    ): void {
        if ($configuration->isInDistributionServices($distribution->getDistributionService())) {
            return;
        }

        $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath('distributionService')
            ->addViolation();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateBlockedBy(
        AssetFile $assetFile,
        ExtSystemAssetTypeDistributionConfiguration $configuration,
        Distribution $distribution
    ): void {
        /** @var ExtSystemAssetTypeDistributionRequirementConfiguration $requirements */
        $requirements =
            $configuration->getDistributionRequirements()[$distribution->getDistributionService()]
            ?? throw new InvalidArgumentException(
                sprintf(
                    'Invalid distribution requirement (%s)',
                    $distribution->getDistributionService()
                )
            );

        foreach ($distribution->getBlockedBy() as $blockedByDistribution) {
            // checks if blockedBy is not supported for current distribution
            if (false === in_array($blockedByDistribution->getDistributionService(), $requirements->getBlockedBy(), true)) {
                $this->context->buildViolation(self::BLOCKED_BY_NOT_SUPPORTED)
                    ->atPath('blockedBy')
                    ->addViolation();
            }

            // checks if blockedBy is used for another asset file version
            if (false === ($assetFile->getId() === $distribution->getAssetFileId())) {
                $this->context->buildViolation(self::BLOCKED_BY_INVALID_ASSET_FILE)
                    ->atPath('blockedBy')
                    ->addViolation();
            }
        }

        match ($requirements->getStrategy()) {
            DistributionRequirementStrategy::None => null,
            DistributionRequirementStrategy::AtLeastOne => $this->validateAtLeastOneStrategy($distribution),
            DistributionRequirementStrategy::WaitForAll => $this->validateWaitForAllStrategy($distribution, $requirements),
        };
    }

    private function validateAtLeastOneStrategy(
        Distribution $distribution,
    ): void {
        if ($distribution->getBlockedBy()->isEmpty()) {
            $this->context->buildViolation(self::BLOCKED_BY_STRATEGY)
                ->atPath('blockedBy')
                ->addViolation();
        }
    }

    private function validateWaitForAllStrategy(
        Distribution $distribution,
        ExtSystemAssetTypeDistributionRequirementConfiguration $requirements
    ): void {
        $blockedByServices = $distribution->getBlockedBy()->map(
            fn (Distribution $distribution): string => $distribution->getDistributionService()
        )->toArray();

        foreach ($requirements->getBlockedBy() as $blockedByRequirement) {
            if (false === in_array($blockedByRequirement, $blockedByServices, true)) {
                $this->context->buildViolation(self::BLOCKED_BY_STRATEGY)
                    ->atPath('blockedBy')
                    ->addViolation();
            }
        }
    }
}
