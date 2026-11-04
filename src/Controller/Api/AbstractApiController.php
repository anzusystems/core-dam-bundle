<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api;

use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use Doctrine\Common\Collections\Collection;
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

    protected function conflictResponse(): JsonResponse
    {
        return new JsonResponse(null, JsonResponse::HTTP_CONFLICT);
    }

    /**
     * @template TKey of array-key
     * @template T
     *
     * @param Collection<TKey, T> $subjects
     *
     * @return Collection<TKey, T> keys of the subjects are kept
     */
    protected function filterGranted(string $attribute, Collection $subjects): Collection
    {
        /** @var AccessDenier $accessDenier */
        $accessDenier = $this->container->get(AccessDenier::class);

        return $accessDenier->filterGranted($attribute, $subjects);
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
