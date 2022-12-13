<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api;

use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * @method DamUser getUser()
 */
#[AsController]
abstract class AbstractApiController extends AbstractAnzuApiController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            AccessDenier::class => AccessDenier::class,
        ]);
    }

    protected function getResponse(
        array | object $data,
        int $statusCode = JsonResponse::HTTP_OK,
    ): JsonResponse {
        return new JsonResponse(
            $this->serializer->serialize($data),
            $statusCode,
            [],
            true
        );
    }

    protected function denyAccessUnlessGranted(
        mixed $attribute,
        mixed $subject = null,
        string $message = 'Access Denied.'
    ): void {
        /** @var AccessDenier $accessDenier */
        $accessDenier = $this->container->get(AccessDenier::class);
        $accessDenier->denyUnlessGranted($attribute, $subject, $message);
    }
}
