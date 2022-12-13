<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/audio', name: 'audio_')]
final class AudioController extends AbstractImageController
{
    public function __construct(
    ) {
    }

    #[Route(
        path: '/{audioId}',
        name: 'get_one',
        requirements: [
            'audioId' => '[0-9a-zA-Z-]+',
        ],
        methods: ['GET']
    )]
    public function getOne(
        string $audioId,
    ): Response {
        return $this->notFoundResponse();
    }
}
