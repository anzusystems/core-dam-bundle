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
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\KeywordAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Exception\KeywordExistsException;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/keyword', name: 'adm_keyword_v1_')]
#[OA\Tag('Keyword')]
final class KeywordController extends AbstractApiController
{
    public function __construct(
        private readonly KeywordFacade $keywordFacade,
        private readonly ElasticSearch $elasticSearch,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{keyword}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('keyword'), OAResponse(Keyword::class)]
    public function getOne(Keyword $keyword): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_KEYWORD_READ, $keyword);

        return $this->okResponse($keyword);
    }

    /**
     * @throws SerializerException
     */
    #[Route('/ext-system/{extSystem}/search', name: 'search_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched.'), OAResponse([Keyword::class])]
    public function searchByExtSystem(ExtSystem $extSystem, #[SerializeParam] KeywordAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_KEYWORD_READ, $extSystem);

        return $this->okResponse($this->elasticSearch->searchInfiniteList($searchDto, $extSystem));
    }

    /**
     * Create one item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(Keyword::class), OAResponse(Author::class), OAResponseValidation]
    public function create(#[SerializeParam] Keyword $keyword): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_KEYWORD_CREATE, $keyword);

        try {
            return $this->createdResponse($this->keywordFacade->create($keyword));
        } catch (KeywordExistsException $exception) {
            return $this->okResponse($exception->getExistingKeyword());
        }
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{keyword}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('keyword'), OARequest(Keyword::class), OAResponse(Keyword::class), OAResponseValidation]
    public function update(Keyword $keyword, #[SerializeParam] Keyword $newKeyword): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_KEYWORD_UPDATE, $keyword);

        return $this->okResponse($this->keywordFacade->update($keyword, $newKeyword));
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{keyword}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('keyword'), OAResponseDeleted]
    public function delete(Keyword $keyword): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_KEYWORD_DELETE, $keyword);
        $this->keywordFacade->delete($keyword);

        return $this->noContentResponse();
    }
}
