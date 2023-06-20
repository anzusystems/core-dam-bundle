<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\TokenStorage;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\RefreshTokenDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Google\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'anzu-dam:youtube:manage-token',
    description: 'Synchronize ext system by base configuration.'
)]
final class YtTokenManageCommand extends Command
{
    use OutputUtilTrait;

    private const ARG_EXT_SYSTEM_SLUG = 'service';

    private const OPT_SHOW_TOKENS = 'show';
    private const OPT_AUTH_URL = 'authUrl';
    private const OPT_LOGOUT = 'logout';
    private const OPT_REFRESH_TOKENS = 'refresh';
    private const OPT_REFRESH_TOKEN = 'refreshToken';

    public function __construct(
        private readonly TokenStorage $tokenStorage,
        private readonly YoutubeAuthenticator $authenticator,
        private readonly DistributionConfigurationProvider $configurationProvider
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addArgument(
                name: self::ARG_EXT_SYSTEM_SLUG,
                mode: InputArgument::REQUIRED,
            )
            ->addOption(
                self::OPT_SHOW_TOKENS,
                null,
                InputOption::VALUE_NONE,
                'Print YT tokens for service',
            )
            ->addOption(
                self::OPT_AUTH_URL,
                null,
                InputOption::VALUE_NONE,
                'Print auth URL',
            )
            ->addOption(
                self::OPT_REFRESH_TOKENS,
                null,
                InputOption::VALUE_NONE,
                'Refresh access token with refresh token',
            )
            ->addOption(
                self::OPT_LOGOUT,
                null,
                InputOption::VALUE_NONE,
                'Clear tokens',
            )
            ->addOption(
                self::OPT_REFRESH_TOKEN,
                null,
                InputOption::VALUE_REQUIRED,
                'Updates refresh token.',
            )
        ;
    }

    /**
     * @throws SerializerException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceName = (string) $input->getArgument(self::ARG_EXT_SYSTEM_SLUG);
        $this->configurationProvider->getYoutubeDistributionService($serviceName);

        if ($input->getOption(self::OPT_AUTH_URL)) {
            $this->outputUtil->info('Auth URL:');
            $this->outputUtil->writeln($this->authenticator->generateAuthUrl($serviceName));
        }
        if ($input->getOption(self::OPT_REFRESH_TOKENS)) {
            $refreshToken = $this->tokenStorage->getRefreshToken($serviceName);

            if (null === $refreshToken) {
                $this->outputUtil->error('Refresh token missing');

                return Command::FAILURE;
            }

            $this->authenticator->refreshAccessToken($refreshToken);
        }
        if ($input->getOption(self::OPT_SHOW_TOKENS)) {
            $this->printToken($serviceName);
        }
        if ($input->getOption(self::OPT_LOGOUT)) {
            $this->outputUtil->info('Clearing tokens for service.');

            try {
                $this->authenticator->logout($serviceName);
            } catch (ForbiddenOperationException) {
                $this->outputUtil->error('Not logged in.');

                return Command::FAILURE;
            }
            $this->outputUtil->info('Done.');
        }

        $refreshToken = $input->getOption(self::OPT_REFRESH_TOKEN);
        if (is_string($refreshToken)) {
            try {
                $this->setAndRefreshToken($serviceName, $refreshToken);

                $this->outputUtil->info('Token stored and refreshed.');
            } catch (Throwable $exception) {
                $this->authenticator->logout($serviceName);
                $this->outputUtil->error('Invalid refresh token. ' . $exception->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    private function setAndRefreshToken(string $serviceName, string $refreshToken): void
    {
        $refreshTokenDto = $this->tokenStorage->storeRefreshToken(
            (new RefreshTokenDto())
                ->setRefreshToken($refreshToken)
                ->setServiceId($serviceName)
                ->setExpiresAt(DateTimeImmutable::createFromMutable((new DateTime())->modify('+ 1 minute')))
        );

        $this->authenticator->refreshAccessToken($refreshTokenDto);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function printToken(string $serviceName): void
    {
        $table = (new Table($this->outputUtil->getOutput()))
            ->setHeaders(['token_type', 'token_value', 'scope', 'service_id', 'expires_at']);

        $accessToken = $this->tokenStorage->getAccessToken($serviceName);
        if ($accessToken) {
            $table->addRow([
                'access_token',
                $accessToken->getAccessToken(),
                str_replace(' ', "\n", $accessToken->getScope()),
                $accessToken->getServiceId(),
                $accessToken->getExpiresAt()->format(DateTimeInterface::ATOM),
            ]);
        }

        $refreshToken = $this->tokenStorage->getRefreshToken($serviceName);
        if ($refreshToken) {
            $table->addRow([
                'refresh_token',
                $refreshToken->getRefreshToken(),
                str_replace(' ', "\n", $refreshToken->getScope()),
                $refreshToken->getServiceId(),
                $refreshToken->getExpiresAt()->format(DateTimeInterface::ATOM),
            ]);
        }

        $table->render();
    }
}
