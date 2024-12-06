<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseFacade;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Repository\AuthorCleanPhraseRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('author-clean-phrase', 'adm_authorCleanPhrase_v1_')]
#[OA\Tag('AuthorCleanPhrase')]
final class AuthorCleanPhraseController extends AbstractApiController
{
    public function __construct(
        private readonly AuthorCleanPhraseFacade $authorCleanPhraseFacade,
        private readonly AuthorCleanPhraseRepository $repository,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{authorCleanPhrase}', 'get_one', ['authorCleanPhrase' => Requirement::DIGITS], methods: [Request::METHOD_GET])]
    #[OAParameterPath('authorCleanPhrase'), OAResponse(AuthorCleanPhrase::class)]
    public function getOne(AuthorCleanPhrase $authorCleanPhrase): JsonResponse
    {
        return $this->okResponse($authorCleanPhrase);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponse([AuthorCleanPhrase::class])]
    public function getList(#[SerializeParam] ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->repository->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Create item.
     *
     * @throws ValidationException|AppReadOnlyModeException
     */
    #[Route('', 'create', methods: [Request::METHOD_POST])]
    #[OARequest(AuthorCleanPhrase::class), OAResponseCreated(AuthorCleanPhrase::class), OAResponseValidation]
    public function create(#[SerializeParam] AuthorCleanPhrase $authorCleanPhrase): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_CLEAN_PHRASE_CREATE);

        return $this->createdResponse(
            $this->authorCleanPhraseFacade->create($authorCleanPhrase)
        );
    }

    /**
     * Update item.
     *
     * @throws ValidationException|AppReadOnlyModeException
     */
    #[Route('/{authorCleanPhrase}', 'update', ['authorCleanPhrase' => Requirement::DIGITS], methods: [Request::METHOD_PUT])]
    #[OAParameterPath('authorCleanPhrase'), OARequest(AuthorCleanPhrase::class), OAResponse(AuthorCleanPhrase::class), OAResponseValidation]
    public function update(AuthorCleanPhrase $authorCleanPhrase, #[SerializeParam] AuthorCleanPhrase $newAuthorCleanPhrase): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_CLEAN_PHRASE_UPDATE);

        return $this->okResponse(
            $this->authorCleanPhraseFacade->update($authorCleanPhrase, $newAuthorCleanPhrase)
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route('/{authorCleanPhrase}', 'delete', ['authorCleanPhrase' => Requirement::DIGITS], methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('authorCleanPhrase'), OAResponseDeleted]
    public function delete(AuthorCleanPhrase $authorCleanPhrase): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUTHOR_CLEAN_PHRASE_DELETE);

        $this->authorCleanPhraseFacade->delete($authorCleanPhrase);

        return $this->noContentResponse();
    }
}