<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Controller\AbstractJobController;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/job', name: 'adm_job_v1_')]
final class JobController extends AbstractJobController
{
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
