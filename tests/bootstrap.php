<?php

declare(strict_types=1);

use AnzuSystems\CoreDamBundle\Tests\AnzuTestKernel;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');

$kernel = new AnzuTestKernel(
    appNamespace: getenv('APP_NAMESPACE'),
    appSystem: getenv('APP_SYSTEM'),
    appVersion: getenv('APP_VERSION'),
    appReadOnlyMode: (bool) getenv('APP_READ_ONLY_MODE'),
    environment: getenv('APP_ENV'),
    debug: (bool) getenv('APP_DEBUG')
);
$kernel->boot();

$app = new Application($kernel);
$app->setAutoExit(false);

$output = new ConsoleOutput();

# Clear cache
$input = new ArrayInput([
    'command' => 'cache:clear',
    '--no-warmup' => true,
    '--env' => getenv('APP_ENV'),
]);
$input->setInteractive(false);
$app->run($input, $output);

return 0;

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
    '--complete' => true,
]);
$input->setInteractive(false);
$app->run($input, $output);

# Console user
$input = new ArrayInput([
    'command' => 'doctrine:query:sql',
    'sql' => "INSERT INTO user (id, created_by_id, modified_by_id, email, roles, permissions, person_first_name, person_last_name,
                              person_full_name, avatar_color, avatar_text, created_at, modified_at, enabled,
                              allowed_asset_external_providers, allowed_distribution_services)
            VALUES (2, 2, 2, 'anonymous@anzusystems.sk', '[]', '[]', '', '', 'Anzu App Anon', '#3F51B5', '',
                    NOW(), NOW(), 0, '[]', '[]')"
]);
$input->setInteractive(false);
$app->run($input, $output);


# Database fixtures
$input = new ArrayInput([
    'command' => 'anzusystems:fixtures:generate'
]);
$input->setInteractive(false);
$app->run($input, $output);

# Elastic index rebuild
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'index-name' => 'asset',
]);
$input->setInteractive(false);
$app->run($input, $output);
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'index-name' => 'author',
]);
$input->setInteractive(false);
$app->run($input, $output);
$input = new ArrayInput([
    'command' => 'anzu-dam:elastic:rebuild',
    'index-name' => 'keyword',
]);
$input->setInteractive(false);
$app->run($input, $output);

# Categories sync
$input = new ArrayInput([
    'command' => 'anzu-dam:distribution:sync-category-select',
]);
$input->setInteractive(false);
$app->run($input, $output);

