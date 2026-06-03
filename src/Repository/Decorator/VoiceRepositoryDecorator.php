<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Repository\VoiceRepository;

final class VoiceRepositoryDecorator
{
    public function __construct(
        private readonly VoiceRepository $voiceRepository,
    ) {
    }

    public function findByFamily(VoiceFamily $family): ApiResponseList
    {
        $voices = $this->voiceRepository->findAllByFamily($family);

        return (new ApiResponseList())
            ->setTotalCount(count($voices))
            ->setData($voices);
    }
}
