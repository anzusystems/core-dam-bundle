<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use AnzuSystems\CoreDamBundle\Exception\ValidationException as DamValidationException;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;

final class AssetLicenceFacadeTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

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

    public function testUpdatePersistsSingleUseEnforced(): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState(
            $licence,
            manualUploadAllowed: true,
            directUseAllowed: true,
            autoDeleteActive: false,
            olderThanDays: 1,
            singleUseEnforced: true,
        );

        $this->assetLicenceFacade->update($licence, $newLicence);
        $this->entityManager->clear();

        self::assertTrue($this->getFixtureLicence()->getFlags()->isSingleUseEnforced());
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

    public function testUpdateAllowsRetentionAtMinimumOlderThanDaysBoundary(): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState(
            $licence,
            manualUploadAllowed: true,
            directUseAllowed: true,
            autoDeleteActive: true,
            olderThanDays: AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS,
        );

        $this->assetLicenceFacade->update($licence, $newLicence);
        $this->entityManager->clear();

        $stored = $this->getFixtureLicence();
        self::assertTrue($stored->getAutoDelete()->isActive());
        self::assertSame(AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS, $stored->getAutoDelete()->getOlderThanDays());
    }

    public function testUpdateSetsDefaultSource(): void
    {
        $licence = $this->getFixtureLicence();
        $source = $this->createSource('Licence Default Source', ExtSystemFixtures::ID_BLOG);
        $newLicence = $this->buildNewLicenceState($licence, manualUploadAllowed: true, directUseAllowed: true, autoDeleteActive: false, olderThanDays: 1);
        $newLicence->setDefaultSource($source);

        $this->assetLicenceFacade->update($licence, $newLicence);
        $this->entityManager->clear();

        $stored = $this->getFixtureLicence();
        self::assertSame($source->getId(), $stored->getDefaultSource()?->getId());
    }

    public function testDefaultSourceFromForeignExtSystemFailsValidation(): void
    {
        $this->assertDefaultSourceIsInvalid($this->createSource('Licence Foreign Source'), DamValidationException::ERROR_INVALID_EXT_SYSTEM);
    }

    public function testDefaultSourceThatIsNotASourceFailsValidation(): void
    {
        $author = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_BLOG_1);
        self::assertInstanceOf(Author::class, $author);

        $this->assertDefaultSourceIsInvalid($author, ValidationException::ERROR_FIELD_INVALID);
    }

    private function assertDefaultSourceIsInvalid(Author $defaultSource, string $expectedError): void
    {
        $licence = $this->getFixtureLicence();
        $newLicence = $this->buildNewLicenceState($licence, manualUploadAllowed: true, directUseAllowed: true, autoDeleteActive: false, olderThanDays: 1);
        $newLicence->setDefaultSource($defaultSource);

        try {
            $this->assetLicenceFacade->update($licence, $newLicence);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertSame([$expectedError], $exception->getFormattedErrors()['defaultSource'] ?? null);
        }
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
        bool $singleUseEnforced = false,
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
            ->setSingleUseEnforced($singleUseEnforced)
        ;
        $newLicence->getAutoDelete()
            ->setActive($autoDeleteActive)
            ->setOlderThanDays($olderThanDays)
        ;

        return $newLicence;
    }
}
