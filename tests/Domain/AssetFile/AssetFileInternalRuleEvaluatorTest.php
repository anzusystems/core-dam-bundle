<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileInternalRuleEvaluator;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use DateTimeImmutable;

final class AssetFileInternalRuleEvaluatorTest extends CoreDamKernelTestCase
{
    private AssetFileInternalRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new AssetFileInternalRuleEvaluator();
    }

    public function testReturnsNullWhenOverrideInternalIsTrue(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getFlags()->setOverrideInternal(true);

        $this->assertNull($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsNullWhenRuleIsInactive(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        // Default fixture has active=false.
        $this->assertFalse($image->getLicence()->getInternalRule()->isActive());
        $this->assertNull($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenRuleActiveAndNoConstraints(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getLicence()->getInternalRule()->setActive(true);

        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsFalseWhenCreatedBeforeMarkAsInternalSince(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getLicence()->getInternalRule()->setActive(true);
        // Set the cutoff date to the future so the file's createdAt is before it.
        $image->getLicence()->getInternalRule()->setMarkAsInternalSince(
            new DateTimeImmutable('+1 year')
        );

        $this->assertFalse($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenCreatedAfterMarkAsInternalSince(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getLicence()->getInternalRule()->setActive(true);
        // Set the cutoff date to the past so the file's createdAt is after it.
        $image->getLicence()->getInternalRule()->setMarkAsInternalSince(
            new DateTimeImmutable('2000-01-01')
        );

        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenAllAssetAuthorsMatchRuleAuthors(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        /** @var Author $author1 */
        $author1 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1);
        $asset->addAuthor($author1);
        $licence->addInternalRuleAuthor($author1);

        $this->entityManager->flush();

        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsFalseWhenAssetAuthorNotInRuleAuthors(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        /** @var Author $author1 */
        $author1 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1);
        /** @var Author $author2 */
        $author2 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_2);

        // Asset has author2, but the rule only allows author1.
        $asset->addAuthor($author2);
        $licence->addInternalRuleAuthor($author1);

        $this->entityManager->flush();

        $this->assertFalse($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenRuleAuthorsEmptyAndAssetHasAuthors(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getLicence()->getInternalRule()->setActive(true);

        /** @var Author $author1 */
        $author1 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1);
        $asset->addAuthor($author1);

        $this->entityManager->flush();

        // No rule authors means the author check is skipped entirely.
        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenCreatedByUserMatchesRuleUsers(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        $createdBy = $image->getCreatedBy();
        $licence->addInternalRuleUser($createdBy);

        $this->entityManager->flush();

        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsFalseWhenCreatedByUserNotInRuleUsers(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        // Add a different user to the rule users — not the file's createdBy.
        $differentUser = $this->entityManager->find(User::class, User::ID_BLOG_USER);
        $licence->addInternalRuleUser($differentUser);

        $this->entityManager->flush();

        $this->assertFalse($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsTrueWhenBothAuthorAndUserMatch(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        /** @var Author $author1 */
        $author1 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1);
        $asset->addAuthor($author1);
        $licence->addInternalRuleAuthor($author1);

        $createdBy = $image->getCreatedBy();
        $licence->addInternalRuleUser($createdBy);

        $this->entityManager->flush();

        $this->assertTrue($this->evaluator->evaluate($asset, $image));
    }

    public function testReturnsFalseWhenAuthorMatchesButUserDoesNot(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();
        $licence = $image->getLicence();

        $licence->getInternalRule()->setActive(true);

        /** @var Author $author1 */
        $author1 = $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1);
        $asset->addAuthor($author1);
        $licence->addInternalRuleAuthor($author1);

        // Add a different user — not the file's createdBy.
        $differentUser = $this->entityManager->find(User::class, User::ID_BLOG_USER);
        $licence->addInternalRuleUser($differentUser);

        $this->entityManager->flush();

        $this->assertFalse($this->evaluator->evaluate($asset, $image));
    }

    public function testEvaluateAndApplySetsInternalFlag(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        $image->getLicence()->getInternalRule()->setActive(true);
        $image->getFlags()->setInternal(false);

        $this->evaluator->evaluateAndApply($asset, $image);

        $this->assertTrue($image->getFlags()->isInternal());
    }

    public function testEvaluateAndApplyDoesNotChangeWhenResultIsNull(): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $asset = $image->getAsset();

        // Rule inactive → evaluate returns null → flag should not change.
        $image->getFlags()->setInternal(false);

        $this->evaluator->evaluateAndApply($asset, $image);

        $this->assertFalse($image->getFlags()->isInternal());
    }
}
