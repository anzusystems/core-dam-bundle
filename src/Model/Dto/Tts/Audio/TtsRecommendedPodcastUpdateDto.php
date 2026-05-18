<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class TtsRecommendedPodcastUpdateDto
{
    #[Serialize]
    private bool $include = false;

    public function isInclude(): bool
    {
        return $this->include;
    }

    public function setInclude(bool $include): self
    {
        $this->include = $include;

        return $this;
    }
}
