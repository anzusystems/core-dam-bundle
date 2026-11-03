<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class JobImageCopyFacade
{
    public function __construct(
        private JobImageCopyManager $manager,
        private Validator $validator,
        private JobImageCopyFactory $imageCopyFactory,
        private AccessDenier $accessDenier,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(JobImageCopy $job): JobInterface
    {
        $this->validator->validate($job);
        $this->manager->create($job);

        return $job;
    }

    /**
     * @param Collection<array-key, Asset> $assets
     */
    public function createPodcastSynchronizerJob(AssetLicence $licence, Collection $assets): JobImageCopy
    {
        $job = $this->imageCopyFactory->createPodcastSynchronizerJob($licence, $assets);
        $this->manager->create($job);

        return $job;
    }

    /**
     * @throws ValidationException
     * @throws AccessDeniedException
     */
    public function createFromCopyList(JobImageCopyRequestDto $dto): JobImageCopy
    {
        $this->validator->validate($dto);
        $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $dto->getTargetAssetLicence());
        foreach ($this->getSourceLicences($dto) as $licence) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_READ, $licence);
        }

        $job = $this->imageCopyFactory->createFromCopyList($dto);

        $this->manager->create($job);

        return $job;
    }

    /**
     * @return array<int, AssetLicence>
     */
    private function getSourceLicences(JobImageCopyRequestDto $dto): array
    {
        $licences = [];
        foreach ($dto->getItems() as $item) {
            $licence = $item->getImageFile()->getLicence();
            $licences[(int) $licence->getId()] = $licence;
        }

        return $licences;
    }
}
