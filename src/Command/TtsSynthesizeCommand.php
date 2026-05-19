<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Tts\Command\DispatchNewAudioNarration;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsNarrationRequestHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reuses the production dispatch + handler path with Messenger bypass (`dispatch: false` +
 * in-process handler invoke) — same code, no Pub/Sub round-trip.
 */
#[AsCommand(
    name: 'anzu-dam:tts:synthesize',
    description: 'Run a TTS synthesis end-to-end in-process (no Messenger). Reuses HTTP-path logic.',
)]
final class TtsSynthesizeCommand extends Command
{
    private const string ARG_TEXT = 'text';
    private const string OPT_FILE = 'file';
    private const string OPT_EXT_SYSTEM = 'ext-system';
    private const string OPT_ASSET_LICENCE = 'asset-licence';
    private const string OPT_VOICE_FAMILY_SLUG = 'voice-family-slug';
    private const string OPT_TITLE = 'title';
    private const string OPT_EXT_RESOURCE_NAME = 'ext-resource-name';
    private const string OPT_EXT_ID = 'ext-id';
    private const string OPT_INCLUDE_RECOMMENDED = 'include-recommended';

    private const string DEFAULT_EXT_SYSTEM = 'cms';
    private const string DEFAULT_ASSET_LICENCE = '100150';

    public function __construct(
        private readonly DispatchNewAudioNarration $dispatchNew,
        private readonly TtsNarrationRequestHandler $handler,
        private readonly TtsNarrationRequestRepository $requestRepo,
        private readonly ExtSystemRepository $extSystemRepo,
        private readonly AssetLicenceRepository $licenceRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_TEXT,
                InputArgument::OPTIONAL,
                'Text to synthesize. Omit when --file is provided.',
            )
            ->addOption(
                self::OPT_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to a UTF-8 text file. Alternative to the positional text argument.',
            )
            ->addOption(
                self::OPT_EXT_SYSTEM,
                null,
                InputOption::VALUE_REQUIRED,
                'ExtSystem slug owning the asset licence.',
                self::DEFAULT_EXT_SYSTEM,
            )
            ->addOption(
                self::OPT_ASSET_LICENCE,
                null,
                InputOption::VALUE_REQUIRED,
                'AssetLicence id under which the resulting Asset will be created.',
                self::DEFAULT_ASSET_LICENCE,
            )
            ->addOption(
                self::OPT_VOICE_FAMILY_SLUG,
                null,
                InputOption::VALUE_REQUIRED,
                'VoiceFamily slug. When omitted, the system default family is used.',
            )
            ->addOption(
                self::OPT_TITLE,
                null,
                InputOption::VALUE_REQUIRED,
                'Optional title attached to the resulting Asset.',
            )
            ->addOption(
                self::OPT_EXT_RESOURCE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'External resource name for idempotency. Must be paired with --ext-id.',
            )
            ->addOption(
                self::OPT_EXT_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'External id for idempotency. Must be paired with --ext-resource-name.',
            )
            ->addOption(
                self::OPT_INCLUDE_RECOMMENDED,
                null,
                InputOption::VALUE_NONE,
                'Mark the resulting asset for the recommended-podcast pool.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $text = $this->resolveText($input, $io);
        if (null === $text) {
            return Command::FAILURE;
        }

        $extSystem = $this->extSystemRepo->findOneBySlug((string) $input->getOption(self::OPT_EXT_SYSTEM));
        if (null === $extSystem) {
            $io->error(sprintf('ExtSystem "%s" not found.', (string) $input->getOption(self::OPT_EXT_SYSTEM)));

            return Command::FAILURE;
        }

        $licence = $this->resolveLicence($input, $extSystem, $io);
        if (null === $licence) {
            return Command::FAILURE;
        }

        $dto = $this->buildDto($input, $text, $licence);

        $io->section('Dispatching synthesis request');
        $io->definitionList(
            ['ext-system' => $extSystem->getSlug()],
            ['asset-licence' => (string) $licence->getId()],
            ['voice-family-slug' => $dto->getVoiceFamilySlug() ?? '<system default>'],
            ['text-length' => (string) mb_strlen($text)],
            ['ext-ref' => null === $dto->getExtResourceName() ? '<none>' : sprintf('%s/%s', $dto->getExtResourceName(), $dto->getExtId())],
        );

        $result = $this->dispatchNew->execute($dto, $licence, dispatch: false);

        if (null === $result->requestId) {
            $io->warning(sprintf(
                'No new request created — kind=%s, existingAssetId=%s.',
                $result->kind->value,
                $result->existingAssetId ?? '<none>',
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf('Request created: %s — running orchestrator in-process…', $result->requestId));

        $this->handler->__invoke(new TtsNarrationRequestMessage($result->requestId));

        $finalRequest = $this->requestRepo->find($result->requestId);
        if (null === $finalRequest) {
            $io->error('Request disappeared after processing.');

            return Command::FAILURE;
        }

        $io->section('Result');
        $io->definitionList(
            ['status' => $finalRequest->getStatus()->value],
            ['resultAssetId' => $finalRequest->getResultAssetId() ?? '<none>'],
            ['failureReason' => $finalRequest->getFailureReason() ?? '<none>'],
        );

        return $finalRequest->getStatus()->is(TtsRequestStatus::Done) ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveText(InputInterface $input, SymfonyStyle $io): ?string
    {
        $filePath = $input->getOption(self::OPT_FILE);
        if (null !== $filePath) {
            if (false === is_readable((string) $filePath)) {
                $io->error(sprintf('File "%s" is not readable.', $filePath));

                return null;
            }

            return (string) file_get_contents((string) $filePath);
        }

        $text = (string) $input->getArgument(self::ARG_TEXT);
        if ('' === $text) {
            $io->error('Provide either the text argument or --file=<path>.');

            return null;
        }

        return $text;
    }

    private function resolveLicence(InputInterface $input, ExtSystem $extSystem, SymfonyStyle $io): ?AssetLicence
    {
        $licenceId = (int) $input->getOption(self::OPT_ASSET_LICENCE);
        $licence = $this->licenceRepo->find($licenceId);
        if (null === $licence) {
            $io->error(sprintf('AssetLicence "%d" not found.', $licenceId));

            return null;
        }

        if ($licence->getExtSystem()->getSlug() !== $extSystem->getSlug()) {
            $io->error(sprintf(
                'AssetLicence "%d" belongs to ExtSystem "%s", not "%s".',
                $licenceId,
                $licence->getExtSystem()->getSlug(),
                $extSystem->getSlug(),
            ));

            return null;
        }

        return $licence;
    }

    private function buildDto(InputInterface $input, string $text, AssetLicence $licence): TtsSynthesizeRequestDto
    {
        return (new TtsSynthesizeRequestDto())
            ->setText($text)
            ->setVoiceFamilySlug($input->getOption(self::OPT_VOICE_FAMILY_SLUG))
            ->setTitle($input->getOption(self::OPT_TITLE))
            ->setExtResourceName($input->getOption(self::OPT_EXT_RESOURCE_NAME))
            ->setExtId($input->getOption(self::OPT_EXT_ID))
            ->setIncludeInRecommendedPodcast((bool) $input->getOption(self::OPT_INCLUDE_RECOMMENDED))
            ->setAssetLicence($licence)
        ;
    }
}
