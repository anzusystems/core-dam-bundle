<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Command\TtsCleanupStuckCommand;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The cron recovers a request abandoned in Waiting (dispatch/plan message lost): once it is older than the
 * threshold, {@see TtsCleanupStuckCommand} fails it so the idempotency key frees up for a fresh dispatch.
 */
final class TtsCleanupStuckCommandTest extends AbstractTtsFunctionalTestCase
{
    private TtsNarrationRequestRepository $requestRepo;
    private TtsCleanupStuckCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestRepo = $this->getService(TtsNarrationRequestRepository::class);
        $this->command = $this->getService(TtsCleanupStuckCommand::class);
    }

    public function testStuckWaitingRequestIsFailed(): void
    {
        $requestId = (string) $this->dispatchWaitingRequest('A short narration that gets stuck.')->getId();

        // Backdate it past the cleanup threshold (the worker never claimed it).
        $this->entityManager->createQuery(
            'UPDATE ' . TtsNarrationRequest::class . ' r SET r.modifiedAt = :old WHERE r.id = :id'
        )
            ->setParameter('old', new DateTimeImmutable('-2 hours'))
            ->setParameter('id', $requestId)
            ->execute();

        $exitCode = (new CommandTester($this->command))->execute(['--older-than' => '1h']);
        self::assertSame(0, $exitCode);

        $reloaded = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $reloaded);
        self::assertTrue(
            $reloaded->getStatus()->is(TtsRequestStatus::Failed),
            'A stuck-waiting request must be failed by the cleanup cron.',
        );
    }
}
