<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AuthorAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/author', name: 'adm_author_v1_')]
#[OA\Tag('Author')]
final class AuthorController extends AbstractApiController
{
    public function __construct(
        private readonly AuthorFacade $authorFacade,
        private readonly ElasticSearch $elasticSearch,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{author}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('author'), OAResponse(Author::class)]
    public function getOne(Author $author): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_VIEW, $author);

        return $this->okResponse($author);
    }

    /**
     * @throws SerializerException
     */
    #[Route('/ext-system/{extSystem}/search', name: 'search_by_ext_system', methods: [Request::METHOD_GET])]
    #[ParamConverter('searchDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('search', description: 'Searched.'), OAResponse([Author::class])]
    public function searchByExtSystem(ExtSystem $extSystem, AuthorAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_VIEW, $extSystem);

        return $this->okResponse($this->elasticSearch->searchInfiniteList($searchDto, $extSystem));
    }

    /**
     * Create one item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('author', converter: SerializerParamConverter::class)]
    #[OARequest(Author::class), OAResponse(Author::class), OAResponseValidation]
    public function create(Author $author): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_CREATE, $author);

        return $this->createdResponse(
            $this->authorFacade->create($author)
        );
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{author}', name: 'update', methods: [Request::METHOD_PUT])]
    #[ParamConverter('newAuthor', converter: SerializerParamConverter::class)]
    #[OAParameterPath('author'), OARequest(Author::class), OAResponse(Author::class), OAResponseValidation]
    public function update(Author $author, Author $newAuthor): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_UPDATE, $author);

        return $this->okResponse(
            $this->authorFacade->update($author, $newAuthor)
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{author}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('author'), OAResponseDeleted]
    public function delete(Author $author): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_DELETE, $author);

        $this->authorFacade->delete($author);

        return $this->noContentResponse();
    }
}
