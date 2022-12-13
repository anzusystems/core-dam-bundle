<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\ExchangeCodeStateDto;
use DateTimeImmutable;
use Exception;

final class YoutubeExchangeCodeStateManager
{
    private const TOKEN_EXPIRATION = '+ 15 minutes';

    /**
     * @throws Exception
     */
    public function generateExchangeCodeStateDto(
        string $distributionService,
        int $userId,
        int $loggedUserId
    ): ExchangeCodeStateDto {
        $state = App::generateSecret();

        return (new ExchangeCodeStateDto())
            ->setService($distributionService)
            ->setState($state)
            ->setUserId($userId)
            ->setInitiatorId($loggedUserId)
            ->setExpiresAt(new DateTimeImmutable(self::TOKEN_EXPIRATION))
        ;
    }
}
