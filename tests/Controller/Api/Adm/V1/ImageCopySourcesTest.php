<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures as TestAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\ImageUrl;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Symfony\Component\HttpFoundation\Response;

final class ImageCopySourcesTest extends AbstractApiController
{
    use AuthorTreeTrait;

    public function testCopyToLicenceKeepsSources(): void
    {
        $source = $this->createSource('Copied Source');
        $assetId = $this->addSourceToImage($source);

        $response = $this->getApiClient(User::ID_ADMIN)->patch(
            (new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID))->copy(),
            [[
                'asset' => $assetId,
                'targetAssetLicence' => TestAssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE,
            ]]
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $item = json_decode((string) $response->getContent(), true)[0];
        self::assertSame('copy', $item['result']);
        $this->entityManager->clear();
        $copy = $this->entityManager->find(Asset::class, $item['targetAsset']);
        self::assertInstanceOf(Asset::class, $copy);
        self::assertSame(
            [(string) $source->getId()],
            $copy->getSources()->map(static fn (Author $author): string => (string) $author->getId())->getValues()
        );
    }

    private function addSourceToImage(Author $source): string
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $image);
        $image->getAsset()->addSource($source);
        $this->entityManager->flush();

        return (string) $image->getAsset()->getId();
    }
}
