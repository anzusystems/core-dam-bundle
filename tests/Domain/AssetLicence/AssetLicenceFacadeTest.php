<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;

final class AssetLicenceFacadeTest extends CoreDamKernelTestCase
{
    private AssetLicenceFacade $assetLicenceFacade;
    private AssetLicenceRepository $assetLicenceRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetLicenceFacade = $this->getService(AssetLicenceFacade::class);
        $this->assetLicenceRepository = $this->getService(AssetLicenceRepository::class);
    }

    public function testUpdatePersistsFlagsAndAutoDelete(): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState($licence, manualUploadAllowed: false, directUseAllowed: false, autoDeleteActive: true, olderThanDays: 10);

        $this->assetLicenceFacade->update($licence, $newLicence);
        $this->entityManager->clear();

        $stored = $this->getFixtureLicence();
        self::assertFalse($stored->getFlags()->isManualUploadAllowed());
        self::assertFalse($stored->getFlags()->isDirectUseAllowed());
        self::assertTrue($stored->getAutoDelete()->isActive());
        self::assertSame(10, $stored->getAutoDelete()->getOlderThanDays());
    }

    public function testUpdateRejectsShortRetentionWhenAutoDeleteActive(): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState($licence, manualUploadAllowed: true, directUseAllowed: true, autoDeleteActive: true, olderThanDays: 1);

        try {
            $this->assetLicenceFacade->update($licence, $newLicence);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('autoDelete.olderThanDays', $exception->getFormattedErrors());
        }
    }

    public function testUpdateAllowsShortRetentionWhenAutoDeleteInactive(): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState($licence, manualUploadAllowed: true, directUseAllowed: true, autoDeleteActive: false, olderThanDays: 1);

        $this->assetLicenceFacade->update($licence, $newLicence);
        $this->entityManager->clear();

        $stored = $this->getFixtureLicence();
        self::assertFalse($stored->getAutoDelete()->isActive());
        self::assertSame(1, $stored->getAutoDelete()->getOlderThanDays());
    }

    private function getFixtureLicence(): AssetLicence
    {
        /** @var AssetLicence $licence */
        $licence = $this->assetLicenceRepository->find(AssetLicenceFixtures::LICENCE_ID);

        return $licence;
    }

    private function buildNewLicenceState(
        AssetLicence $licence,
        bool $manualUploadAllowed,
        bool $directUseAllowed,
        bool $autoDeleteActive,
        int $olderThanDays,
    ): AssetLicence {
        $newLicence = (new AssetLicence())
            ->setId($licence->getId())
            ->setName($licence->getName())
            ->setExtId($licence->getExtId())
            ->setExtSystem($licence->getExtSystem())
            ->setInternalRule($licence->getInternalRule())
            ->setInternalRuleAuthors($licence->getInternalRuleAuthors())
            ->setInternalRuleUsers($licence->getInternalRuleUsers())
        ;
        $newLicence->getFlags()
            ->setManualUploadAllowed($manualUploadAllowed)
            ->setDirectUseAllowed($directUseAllowed)
        ;
        $newLicence->getAutoDelete()
            ->setActive($autoDeleteActive)
            ->setOlderThanDays($olderThanDays)
        ;

        return $newLicence;
    }
}
