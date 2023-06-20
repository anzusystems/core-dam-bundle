<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo\JwVideoThumbnail;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Messenger\Message\JwVideoThumbnailPosterMessage;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JwVideoThumbnailPosterHandler
{
    public function __construct(
        private JwVideoThumbnail $jwVideoThumbnail,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws NonUniqueResultException
     * @throws RemoteProcessingWaitingException
     * @throws SerializerException
     */
    public function __invoke(JwVideoThumbnailPosterMessage $message): void
    {
        $this->jwVideoThumbnail->makeThumbnailPoster(
            $message->getThumbnailId(),
            $message->getDistribService()
        );
    }
}
