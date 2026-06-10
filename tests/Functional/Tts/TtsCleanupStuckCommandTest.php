<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Command\TtsCleanupStuckCommand;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsNarrationRequestFixtures;
use Symfony\Component\Console\Tester\CommandTester;

/** Reconciles stuck requests seeded by {@see TtsNarrationRequestFixtures}: fail past hard-cap, resume within. */
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

    public function testStuckWaitingPastHardCapIsFailed(): void
    {
        $requestId = $this->requireStuckWaitingId(TtsNarrationRequestFixtures::FAIL_TEXT);

        (new CommandTester($this->command))->execute(['--older-than' => '1h']);

        $reloaded = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $reloaded);
        self::assertTrue(
            $reloaded->getStatus()->is(TtsRequestStatus::Failed),
            'A request stuck in waiting past the hard cap must be failed by the cleanup cron.',
        );
    }

    public function testStuckWaitingWithinHardCapIsResumedToCompletion(): void
    {
        $requestId = $this->requireStuckWaitingId(TtsNarrationRequestFixtures::RESUME_TEXT);

        (new CommandTester($this->command))->execute(['--older-than' => '1m', '--hard-cap' => '1h']);

        $reloaded = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $reloaded);
        self::assertTrue(
            $reloaded->getStatus()->is(TtsRequestStatus::Done),
            'A recoverable stuck request must be resumed and run to completion (sync transport + mocked provider).',
        );
    }

    /**
     * Loads seeded request and asserts it is still Waiting before the cron runs.
     */
    private function requireStuckWaitingId(string $sourceText): string
    {
        $request = $this->requestRepo->findOneBy(['sourceText' => $sourceText]);
        self::assertInstanceOf(TtsNarrationRequest::class, $request);
        self::assertTrue(
            $request->getStatus()->is(TtsRequestStatus::Waiting),
            'Precondition: the fixture request must start stuck in waiting.',
        );

        return (string) $request->getId();
    }
}
