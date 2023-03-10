<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFacade;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use AnzuSystems\CoreDamBundle\Repository\Decorator\CustomFormElementRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Repository\ResourceCustomFormRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/asset-custom-form', name: 'adm_asset_custom_form_v1_')]
#[OA\Tag('CustomForm')]
final class AssetCustomFormController extends AbstractApiController
{
    public function __construct(
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
        private readonly ResourceCustomFormRepository $resourceCustomFormRepository,
        private readonly CustomFormElementRepositoryDecorator $customFormElementRepositoryDecorator,
        private readonly CustomFormFacade $customFormFacade,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/ext-system/{extSystem}/type/{assetType}', name: 'get_one_by_ext_system_and_type', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetCustomForm::class)]
    public function getOne(ExtSystem $extSystem, AssetType $assetType): JsonResponse
    {
        $form = $this->assetCustomFormRepository->findOneByTypeAndExtSystem($extSystem, $assetType);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_CUSTOM_FORM_VIEW, $form);

        return $form
            ? $this->okResponse($form)
            : throw $this->createNotFoundException();
    }

    /**
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    #[Route(path: '/ext-system/{extSystem}/type/{assetType}/element', name: 'get_elements_by_ext_system_and_type', methods: [Request::METHOD_GET])]
    #[OAResponseList(AssetCustomForm::class)]
    public function getElements(ExtSystem $extSystem, AssetType $assetType, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_CUSTOM_FORM_ELEMENT_VIEW);
        $form = $this->assetCustomFormRepository->findOneByTypeAndExtSystem($extSystem, $assetType);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_CUSTOM_FORM_VIEW, $form);

        if (null === $form) {
            return $this->okResponse(new ApiResponseList());
        }

        return $this->okResponse(
            $this->customFormElementRepositoryDecorator->findByApiParams($apiParams, $form)
        );
    }

    /**
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    #[Route(path: '/distribution-service/{distributionService}/element', name: 'get_elements_by_distribution', methods: [Request::METHOD_GET])]
    #[OAResponseList(AssetCustomForm::class)]
    public function getDistributionElements(string $distributionService, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_CUSTOM_FORM_ELEMENT_VIEW);
        $form = $this->resourceCustomFormRepository->findByResource(CustomFormFactory::getDistributionServiceResourceKey($distributionService));

        if (null === $form) {
            return $this->okResponse(new ApiResponseList());
        }

        return $this->okResponse(
            $this->customFormElementRepositoryDecorator->findByApiParams($apiParams, $form)
        );
    }

    /**
     * @throws ValidationException
     */
    #[Route(path: '/{form}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('form'), OARequest(AssetCustomForm::class), OAResponse(AssetCustomForm::class), OAResponseValidation]
    public function update(AssetCustomForm $form, #[SerializeParam] AssetCustomForm $newForm): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_CUSTOM_FORM_UPDATE, $form);

        return $this->okResponse(
            $this->customFormFacade->update($form, $newForm)
        );
    }
}
