<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetCustomForm\AssetCustomFormSynchronizer;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Doctrine\ORM\NonUniqueResultException;

final class ExtSystemSynchronizer
{
    use OutputUtilTrait;

    public function __construct(
        private readonly ExtSystemManager $extSystemManager,
        private readonly ExtSystemConfigurationProvider $configurationProvider,
        private readonly ExtSystemRepository $extSystemRepository,
        private readonly AssetCustomFormSynchronizer $assetCustomFormSynchronizer,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function synchronizeExtSystems(): void
    {
        $extSystemSlugs = $this->configurationProvider->getExtSystemSlugs();

        foreach ($extSystemSlugs as $extSystemSlug) {
            $extSystem = $this->createIfNotExists($extSystemSlug);
            $this->assetCustomFormSynchronizer->synchronizeForExtSystem($extSystem);
            $this->flushAndClear();
        }

        foreach ($this->extSystemRepository->findAllExcept($extSystemSlugs) as $extSystem) {
            $this->outputUtil->error(sprintf('Ext system has no configuration (%s)', $extSystem->getSlug()));
        }
    }

    private function createIfNotExists(string $slug): ExtSystem
    {
        $extSystem = $this->extSystemRepository->findOneBySlug($slug);

        if ($extSystem) {
            $this->outputUtil->writeln(sprintf('ExtSystem already exists (%s)', $slug));

            return $extSystem;
        }

        $this->outputUtil->info(sprintf('Creating ExtSystem (%s)', $slug));

        return $this->extSystemManager->create(
            extSystem: (new ExtSystem())
                ->setSlug($slug)
                ->setName($slug),
            flush: false
        );
    }

    private function flushAndClear(): void
    {
        $this->extSystemManager->flush();
        $this->extSystemManager->clear();
    }
}
