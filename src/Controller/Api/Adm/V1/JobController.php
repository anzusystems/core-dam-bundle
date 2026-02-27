<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Controller\AbstractJobController;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
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
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            AccessDenier::class => AccessDenier::class,
        ]);
    }

    protected function denyAccessUnlessGranted(
        mixed $attribute,
        mixed $subject = null,
        string $message = 'Access Denied.',
    ): void {
        /** @var AccessDenier $accessDenier */
        $accessDenier = $this->container->get(AccessDenier::class);
        $accessDenier->denyUnlessGranted($attribute, $subject, $message);
    }
    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/podcast-synchronizer', 'create_job_podcast_synchronizer', methods: [Request::METHOD_POST])]
    #[OARequest(JobPodcastSynchronizer::class), OAResponseCreated(JobPodcastSynchronizer::class), OAResponseValidation]
    public function createPodcastSynchronizer(Request $request, #[SerializeParam] JobPodcastSynchronizer $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());
        $job = $this->jobFacade->create($job);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $job);

        return $this->createdResponse($job);
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/image-copy', 'create_job_image_copy', methods: [Request::METHOD_POST])]
    #[OARequest(JobImageCopy::class), OAResponseCreated(JobImageCopy::class), OAResponseValidation]
    public function createImageCopyJob(Request $request, #[SerializeParam] JobImageCopy $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());
        $job = $this->jobFacade->create($job);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $job);

        return $this->createdResponse($job);
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/author-current-optimize', 'create_job_author_current_optimize', methods: [Request::METHOD_POST])]
    #[OARequest(JobAuthorCurrentOptimize::class), OAResponseCreated(JobAuthorCurrentOptimize::class), OAResponseValidation]
    public function createAuthorCurrentJob(Request $request, #[SerializeParam] JobAuthorCurrentOptimize $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());
        $job = $this->jobFacade->create($job);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $job);

        return $this->createdResponse($job);
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/asset-file-reprocess-internal-flag', 'create_job_asset_file_reprocess_internal_flag', methods: [Request::METHOD_POST])]
    #[OARequest(JobAssetFileReprocessInternalFlag::class), OAResponseCreated(JobAssetFileReprocessInternalFlag::class), OAResponseValidation]
    public function createAssetFileReprocessInternalFlagJob(
        Request $request,
        #[SerializeParam]
        JobAssetFileReprocessInternalFlag $job,
    ): JsonResponse {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getCreateAcl());
        $job = $this->jobFacade->create($job);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $job);

        return $this->createdResponse($job);
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
