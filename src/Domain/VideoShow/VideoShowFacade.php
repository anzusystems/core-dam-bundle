<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShow;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;

final class VideoShowFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly VideoShowManager $videoShowManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(VideoShow $videoShow): VideoShow
    {
        $this->validator->validate($videoShow);
        $this->videoShowManager->create($videoShow);

        return $videoShow;
    }

    /**
     * @throws ValidationException
     */
    public function update(VideoShow $videoShow, VideoShow $newVideoShow): VideoShow
    {
        $this->validator->validate($newVideoShow, $videoShow);
        $this->videoShowManager->update($videoShow, $newVideoShow);

        return $videoShow;
    }

    public function delete(VideoShow $videoShow): bool
    {
        $this->videoShowManager->delete($videoShow);

        return true;
    }
}
