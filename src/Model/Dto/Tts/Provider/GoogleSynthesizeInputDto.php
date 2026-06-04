<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class GoogleSynthesizeInputDto
{
    #[Serialize]
    private string $text = '';

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
