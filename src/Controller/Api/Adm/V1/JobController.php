<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Controller\AbstractJobController;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/job', name: 'adm_job_v1_')]
final class JobController extends AbstractJobController
{
    /**
     * Create JobPodcastSynchronizer item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/podcast-synchronizer', 'create_job_podcast_synchronizer', methods: [Request::METHOD_POST])]
    #[OARequest(JobPodcastSynchronizer::class), OAResponseCreated(JobPodcastSynchronizer::class), OAResponseValidation]
    public function createPodcastSynchronizer(#[SerializeParam] JobPodcastSynchronizer $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());

        return $this->createdResponse(
            $this->jobFacade->create($job)
        );
    }

    /**
     * Create JobPodcastSynchronizer item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/image-copy', 'create_job_image_copy', methods: [Request::METHOD_POST])]
    #[OARequest(JobImageCopy::class), OAResponseCreated(JobImageCopy::class), OAResponseValidation]
    public function createImageCopyJob(#[SerializeParam] JobImageCopy $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());

        return $this->createdResponse(
            $this->jobFacade->create($job)
        );
    }

    protected function getCreateAcl(): string
    {
        return DamPermissions::DAM_JOB_CREATE;
    }

    protected function getDeleteAcl(): string
    {
        return DamPermissions::DAM_JOB_DELETE;
    }

    protected function getViewAcl(): string
    {
        return DamPermissions::DAM_JOB_VIEW;
    }
}
