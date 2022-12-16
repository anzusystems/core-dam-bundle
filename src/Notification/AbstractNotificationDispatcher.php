<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Notification;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractNotificationDispatcher
{
    use SerializerAwareTrait;

    protected CurrentAnzuUserProvider $currentUserProvider;
    protected ConfigurationProvider $configurationProvider;
    private CacheItemPoolInterface $coreDamBundlePubSubTokenCache;


    #[Required]
    public function setCurrentUserProvider(CurrentAnzuUserProvider $currentUserProvider): void
    {
        $this->currentUserProvider = $currentUserProvider;
    }

    #[Required]
    public function setConfigurationProvider(ConfigurationProvider $configurationProvider): void
    {
        $this->configurationProvider = $configurationProvider;
    }

    #[Required]
    public function setCoreDamBundlePubSubTokenCache(CacheItemPoolInterface $coreDamBundlePubSubTokenCache): void
    {
        $this->coreDamBundlePubSubTokenCache = $coreDamBundlePubSubTokenCache;
    }

    /**
     * @param list<int> $userIds
     *
     * @throws SerializerException
     */
    protected function notify(array $userIds, string $eventName, object $data = null): void
    {
        $notificationsConfig = $this->configurationProvider->getSettings()->getNotificationsConfig();
        if ($notificationsConfig->isDisabled()) {
            return;
        }

        $pubSubClient = new PubSubClient([
            ...$notificationsConfig->getGpsConfig(),
            ...['authCache' => $this->coreDamBundlePubSubTokenCache],
        ]);

        $pubSubClient->topic($notificationsConfig->getTopic())->publish(
            new Message([
                'attributes' => [
                    'targetSsoUserIds' => json_encode($userIds),
                    'eventName' => $eventName,
                ],
                'data' => $data ? $this->serializer->serialize($data) : '',
            ])
        );
    }
}
