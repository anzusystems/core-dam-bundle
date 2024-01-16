<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetFileSysUrl;
use Symfony\Component\HttpFoundation\Response;

final class AssetFileControllerTest extends AbstractAssetFileApiController
{
    private const string TEST_DATA_FILENAME = 'metadata_image.jpeg';

    protected ImageUrlFactory $imageUrlFactory;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var ImageUrlFactory $imageUrlFactory */
        $imageUrlFactory = static::getContainer()->get(ImageUrlFactory::class);
        $this->imageUrlFactory = $imageUrlFactory;
    }

    public function testCreate(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);

        $fixturesImagePath = AbstractAssetFileFixtures::DATA_PATH . self::TEST_DATA_FILENAME;
        $fileSystem = $this->filesystemProvider->getFileSystemByStorageName('cms.image');
        $fileSystem->write(self::TEST_DATA_FILENAME, file_get_contents($fixturesImagePath));

        $response = $client->post(AssetFileSysUrl::create(), [
            'licence' => 100_000,
            'path' => self::TEST_DATA_FILENAME,
            'customData' => [
                'title' => 'Titulok',
                'headline' => 'Headline'
            ],
            'generatePublicRoute' => true
        ]);

        $fileSystem->delete(self::TEST_DATA_FILENAME);

        $this->assertEquals($response->getStatusCode(), Response::HTTP_OK);
        $responseData = json_decode($response->getContent(), true);
        $this->assertEqualsCanonicalizing(
            [
                'title' => 'Titulok',
                'headline' => 'Headline',
                'description' => 'Happy elderly man with walking stick and smiling senior people relaxing in the garden',
                'creditLine' => 'Photographee.eu - stock.adobe.co',
                'copyrightNotice' => 'Shaquille Ferguson Photographee.eu',
                'rightsUsageTerms' => 'Shaquille Ferguson Photographee.eu',
            ],
            $responseData['customData']
        );

        $response = $client->get(sprintf('http://image.anzusystems.localhost/image/original/%s.jpg', $responseData['id']));

        $this->assertEquals(
            $response ->getStatusCode(),
            Response::HTTP_OK
        );
    }
}
