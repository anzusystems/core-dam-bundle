<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
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
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use OpenApi\Attributes as OA;
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_READ, $author);

        return $this->okResponse($author);
    }

    /**
     * @throws SerializerException
     */
    #[Route('/ext-system/{extSystem}/search', name: 'search_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched.'), OAResponse([Author::class])]
    public function searchByExtSystem(ExtSystem $extSystem, #[SerializeParam] AuthorAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_READ, $extSystem);

        return $this->okResponse($this->elasticSearch->searchInfiniteList($searchDto, $extSystem));
    }

    /**
     * Create one item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(Author::class), OAResponse(Author::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] Author $author): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_CREATE, $author);

        $author = $this->authorFacade->create($author);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $author);

        return $this->createdResponse($author);
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{author}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('author'), OARequest(Author::class), OAResponse(Author::class), OAResponseValidation]
    public function update(Request $request, Author $author, #[SerializeParam] Author $newAuthor): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_UPDATE, $author);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $author);

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
    public function delete(Request $request, Author $author): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_DELETE, $author);

        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $author);
        $this->authorFacade->delete($author);

        return $this->noContentResponse();
    }
}
