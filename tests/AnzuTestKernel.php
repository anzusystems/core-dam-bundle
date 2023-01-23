<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\CoreDamBundle\AnzuSystemsCoreDamBundle;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\SerializerBundle\AnzuSystemsSerializerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class AnzuTestKernel extends AnzuKernel
{
    protected int $userIdAnonymous =  User::ID_ANONYMOUS;
    protected int $userIdAdmin = User::ID_ADMIN;
    protected int $userIdConsole = User::ID_CONSOLE;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new MonologBundle();
        yield new AnzuSystemsCommonBundle();
        yield new AnzuSystemsSerializerBundle();
        yield new AnzuSystemsCoreDamBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/{packages}/*.yaml');
        $container->import(__DIR__ . '/config/{services}/*.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routing/routing.php');
    }
}
