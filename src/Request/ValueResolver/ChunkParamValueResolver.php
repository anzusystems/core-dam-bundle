<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ValueResolver;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ChunkParamValueResolver implements ValueResolverInterface
{
    use SerializerAwareTrait;

    private const REQUEST_CHUNK_KEY = 'chunk';
    private const REQUEST_FILE_KEY = 'file';

    /**
     * @throws SerializerException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (ChunkAdmCreateDto::class === $argument->getType()) {
            return $this->resolveFromChunk($request);
        }

        return [];
    }

    /**
     * @throws SerializerException
     */
    private function resolveFromChunk(Request $request): iterable
    {
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

        return [$chunkDto];
    }
}
