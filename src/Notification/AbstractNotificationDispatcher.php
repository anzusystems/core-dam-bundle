<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Notification;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractNotificationDispatcher
{
    use SerializerAwareTrait;

    protected CurrentAnzuUserProvider $currentUserProvider;
    protected ConfigurationProvider $configurationProvider;


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

    /**
     * @param list<int> $userIds
     *
     * @throws SerializerException
     */
    protected function notify(array $userIds, string $eventName, object $data = null): void
    {
        $pubSubClient = new PubSubClient();

        $pubSubClient->topic($this->configurationProvider->getNotificationTopic())->publish(
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
