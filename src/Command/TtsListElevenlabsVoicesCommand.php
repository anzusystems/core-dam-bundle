<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\ElevenlabsClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Free-tier keys see only a subset of voices — picking one from this list guarantees no 402
 * ("paid_plan_required") on synthesize. Use the printed `voice_id` to override
 * `App\DataFixtures\TtsVoiceFixtures::DEFAULT_ELEVENLABS_{MALE,FEMALE}_VOICE_ID`.
 */
#[AsCommand(
    name: 'anzu-dam:tts:elevenlabs:list-voices',
    description: 'List ElevenLabs voices accessible with the configured API key (free-tier vs library).',
)]
final class TtsListElevenlabsVoicesCommand extends Command
{
    private const string OPT_EXT_SYSTEM = 'ext-system';
    private const string DEFAULT_EXT_SYSTEM = 'cms';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigProvider,
        private readonly ElevenlabsClient $elevenlabsClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            self::OPT_EXT_SYSTEM,
            null,
            InputOption::VALUE_REQUIRED,
            'ExtSystem slug whose ElevenLabs API key to use.',
            self::DEFAULT_EXT_SYSTEM,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extSystemSlug = (string) $input->getOption(self::OPT_EXT_SYSTEM);

        $apiKey = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystemSlug)->elevenlabsApiKey;
        if ('' === $apiKey) {
            $io->error(sprintf('No ElevenLabs API key configured for ExtSystem "%s".', $extSystemSlug));

            return Command::FAILURE;
        }

        $response = $this->elevenlabsClient->listVoices($apiKey);
        if ($response->hasError()) {
            $io->error(sprintf('ElevenLabs returned HTTP %d. Body: %s', $response->getStatusCode(), $response->getContent()));

            return Command::FAILURE;
        }

        $payload = json_decode($response->getContent(), associative: true);
        if (false === is_array($payload) || false === isset($payload['voices']) || false === is_array($payload['voices'])) {
            $io->error('Unexpected response shape from /v1/voices.');
            $io->writeln($response->getContent());

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($payload['voices'] as $voice) {
            $rows[] = [
                $voice['voice_id'] ?? '?',
                $voice['name'] ?? '?',
                $voice['category'] ?? '?',
                $voice['labels']['gender'] ?? '?',
                $voice['labels']['language'] ?? ($voice['fine_tuning']['language'] ?? '?'),
            ];
        }

        if ([] === $rows) {
            $io->warning('No voices returned. Your key has zero accessible voices.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('%d voice(s) accessible with the "%s" ExtSystem API key', count($rows), $extSystemSlug));
        $io->table(['voice_id', 'name', 'category', 'gender', 'language'], $rows);
        $io->note(
            'Pick any voice_id and override App\\DataFixtures\\TtsVoiceFixtures::DEFAULT_ELEVENLABS_{MALE,FEMALE}_VOICE_ID. '
            . 'category=premade and category=cloned are free-tier safe; category=professional/generated may need a paid plan.',
        );

        return Command::SUCCESS;
    }
}
