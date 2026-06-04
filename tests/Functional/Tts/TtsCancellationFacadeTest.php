<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsCancellationFacade;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;

/**
 * Initial-request cancellation: a Waiting request flips to Cancelled, and a second cancel on the now-terminal
 * request is rejected ({@see TtsRequestStatus::CANCELLABLE_STATUSES} guard).
 */
final class TtsCancellationFacadeTest extends AbstractTtsFunctionalTestCase
{
    private TtsCancellationFacade $cancellationFacade;
    private TtsNarrationRequestRepository $requestRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cancellationFacade = $this->getService(TtsCancellationFacade::class);
        $this->requestRepo = $this->getService(TtsNarrationRequestRepository::class);
    }

    public function testCancelInitialFlipsWaitingToCancelledThenRejectsSecondCancel(): void
    {
        $request = $this->dispatchWaitingRequest('A short narration to cancel.');
        self::assertTrue($request->getStatus()->is(TtsRequestStatus::Waiting));

        $cancelled = $this->cancellationFacade->cancel($request, 'test-user');
        self::assertTrue($cancelled);

        $reloaded = $this->requestRepo->find((string) $request->getId());
        self::assertInstanceOf(TtsNarrationRequest::class, $reloaded);
        self::assertTrue($reloaded->getStatus()->is(TtsRequestStatus::Cancelled));

        // Cancelled is terminal → no longer cancellable.
        $this->expectException(ImmutableAudioNarrationException::class);
        $this->cancellationFacade->cancel($reloaded, 'test-user');
    }
}
