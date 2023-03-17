<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoShowEpisodeFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoShowFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\VideoShowEpisodeUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class AssetCustomFormControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $response = $client->get('/api/adm/v1/asset-custom-form/ext-system/1/type/image');
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $customForm = $this->serializer->deserialize($response->getContent(), AssetCustomForm::class);
        $customFormEntity = $this->entityManager->getRepository(AssetCustomForm::class)->findOneBy([
            'assetType' => AssetType::Image->toString(),
            'extSystem' => 1
        ]);

        $this->assertSame($customForm->getId(), $customFormEntity->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetElementsSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);
        $response = $client->get('/api/adm/v1/asset-custom-form/ext-system/1/type/image/element');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $elements = $client->deserializeApiResponseList($response, CustomFormElement::class);
        $customFormEntity = $this->entityManager->getRepository(AssetCustomForm::class)->findOneBy([
            'assetType' => AssetType::Image->toString(),
            'extSystem' => 1
        ]);

        $this->assertCount(9, $elements);

        /** @var CustomFormElement $element */
        foreach ($elements as $element) {
            /** @var CustomFormElement $entityElement */
            $entityElement = $customFormEntity->getElements()->filter(
                fn (CustomFormElement $entityElement): bool => $entityElement->getId() === $element->getId()
            )->first();

            $this->assertInstanceOf(CustomFormElement::class, $entityElement);
            $this->assertSame($element->getName(), $entityElement->getName());
            $this->assertSame($element->getKey(), $entityElement->getKey());
            $this->assertSame($element->getAttributes()->getMaxCount(), $entityElement->getAttributes()->getMaxCount());
            $this->assertSame($element->getAttributes()->getMinCount(), $entityElement->getAttributes()->getMinCount());
            $this->assertSame($element->getAttributes()->getMaxValue(), $entityElement->getAttributes()->getMaxValue());
            $this->assertSame($element->getAttributes()->getMinValue(), $entityElement->getAttributes()->getMinValue());
            $this->assertSame($element->getAttributes()->getType()->toString(), $entityElement->getAttributes()->getType()->toString());
        }
    }
}
