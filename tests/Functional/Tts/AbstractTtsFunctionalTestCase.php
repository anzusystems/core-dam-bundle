<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsVoiceFixtures;
use Doctrine\Common\Collections\ArrayCollection;

/** Shared dispatch helpers for TTS functional tests. */
abstract class AbstractTtsFunctionalTestCase extends CoreDamKernelTestCase
{
    protected TtsDispatchFacade $dispatchFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatchFacade = $this->getService(TtsDispatchFacade::class);
    }

    protected function buildSynthesizeDto(string $text): TtsSynthesizeRequestDto
    {
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        self::assertInstanceOf(AssetLicence::class, $licence);

        return (new TtsSynthesizeRequestDto())
            ->setText($text)
            ->setAssetLicence($licence)
            ->setVoiceFamilySlug(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG)
            ->setPodcasts(new ArrayCollection());
    }

    /**
     * Dispatches a request with enqueue:false, leaving it in Waiting.
     */
    protected function dispatchWaitingRequest(string $text): TtsNarrationRequest
    {
        $result = $this->dispatchFacade->synthesize($this->buildSynthesizeDto($text), enqueue: false);
        self::assertInstanceOf(TtsNarrationRequest::class, $result->narrationRequest);

        return $result->narrationRequest;
    }
}
