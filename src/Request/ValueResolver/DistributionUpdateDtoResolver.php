<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ValueResolver;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\AbstractDistributionUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\CustomDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionAdmUpdateDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class DistributionUpdateDtoResolver implements ValueResolverInterface
{
    use SerializerAwareTrait;

    /**
     * @throws SerializerException
     * @throws BadRequestHttpException
     * @throws ValidationException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (false === (AbstractDistributionUpdateDto::class === $argument->getType())) {
            return [];
        }

        try {
            $item = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

            if (false === isset($item['_resourceName'])) {
                throw (new ValidationException())
                    ->addFormattedError('_resourceName', ValidationException::ERROR_FIELD_EMPTY);
            }

            if ($item['_resourceName'] === JwDistribution::getResourceName()) {
                return [$this->serializer->fromArray($item, JwDistributionAdmUpdateDto::class)];
            }
            if ($item['_resourceName'] === YoutubeDistribution::getResourceName()) {
                return [$this->serializer->fromArray($item, YoutubeDistributionAdmUpdateDto::class)];
            }
            if ($item['_resourceName'] === Distribution::getResourceName()) {
                return [$this->serializer->fromArray($item, CustomDistributionAdmUpdateDto::class)];
            }

            throw (new ValidationException())
                ->addFormattedError('_resourceName', ValidationException::ERROR_FIELD_INVALID);

        } catch (JsonException) {
            throw new BadRequestHttpException('invalid_body');
        }
    }
}
