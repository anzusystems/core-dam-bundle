<?php

declare(strict_types=1);

use AnzuSystems\CoreDamBundle\Tests\AnzuTestKernel;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');

// these annotations are not required, they are optional
AnnotationReader::addGlobalIgnoredNamespace('OpenApi');
AnnotationReader::addGlobalIgnoredNamespace('Nelmio');

$kernel = new AnzuTestKernel(
    appSystem: getenv('APP_SYSTEM'),
    appVersion: getenv('APP_VERSION'),
    appReadOnlyMode: (bool) getenv('APP_READ_ONLY_MODE'),
    environment: getenv('APP_ENV'),
    debug: (bool) getenv('APP_DEBUG')
);
$kernel->boot();

$app = new Application($kernel);
$app->setAutoExit(false);

//return;

$output = new ConsoleOutput();

# Clear cache
$input = new ArrayInput([
    'command' => 'cache:clear',
    '--no-warmup' => true,
    '--env' => getenv('APP_ENV'),
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database drop
$input = new ArrayInput([
    'command' => 'doctrine:database:drop',
    '--force' => true,
    '--if-exists' => true,
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database create
$input = new ArrayInput([
    'command' => 'doctrine:database:create',
]);
$input->setInteractive(false);
$app->run($input, $output);

# Update schema
$input = new ArrayInput([
    'command' => 'doctrine:schema:update',
    '--force' => true,
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database fixtures
$input = new ArrayInput([
    'command' => 'anzu-dam:fixtures:generate',
]);
$input->setInteractive(false);
$app->run($input, $output);

# Elastic index rebuild
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'indexName' => 'asset',
]);
$input->setInteractive(false);
$app->run($input, $output);
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'indexName' => 'author',
]);
$input->setInteractive(false);
$app->run($input, $output);
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'indexName' => 'keyword',
]);
$input->setInteractive(false);
$app->run($input, $output);

# Categories sync
$input = new ArrayInput([
    'command' => 'anzu-dam:distribution:sync-category-select',
]);
$input->setInteractive(false);
$app->run($input, $output);

