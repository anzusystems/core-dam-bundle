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

/**
 * Shared dispatch helpers for TTS functional tests (default cms licence + seeded default voice family).
 */
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
     * Dispatches an Initial request and leaves it in Waiting — enqueue:false never triggers the worker.
     */
    protected function dispatchWaitingRequest(string $text): TtsNarrationRequest
    {
        $result = $this->dispatchFacade->synthesize($this->buildSynthesizeDto($text), enqueue: false);
        self::assertInstanceOf(TtsNarrationRequest::class, $result->narrationRequest);

        return $result->narrationRequest;
    }
}
