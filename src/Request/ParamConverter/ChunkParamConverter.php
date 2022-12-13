<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ParamConverter;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ChunkParamConverter implements ParamConverterInterface
{
    use SerializerAwareTrait;

    private const REQUEST_CHUNK_KEY = 'chunk';
    private const REQUEST_FILE_KEY = 'file';

    /**
     * @throws SerializerException
     */
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $name = $configuration->getName();
        $chunkJson = $request->request->get(self::REQUEST_CHUNK_KEY);

        if (false === is_string($chunkJson)) {
            throw new BadRequestHttpException('chunk_body_invalid');
        }

        $file = $request->files->get(self::REQUEST_FILE_KEY);

        if (false === ($file instanceof UploadedFile)) {
            throw new BadRequestHttpException('file_missing');
        }

        $chunkDto = $this->serializer->deserialize($chunkJson, ChunkAdmCreateDto::class);
        $chunkDto->setFile($file);
        $request->attributes->set($name, $chunkDto);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return (bool) $configuration->getClass();
    }
}
