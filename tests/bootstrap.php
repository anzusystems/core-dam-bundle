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

# Tables outside of Doctrine, a copy of the host migration core-dam Version20261112130000_author_merge_schema
foreach ([
    'CREATE TABLE _author_merge_log (id INT UNSIGNED AUTO_INCREMENT NOT NULL, run_id VARCHAR(36) NOT NULL, ext_system_id INT NOT NULL, group_name VARCHAR(64) NOT NULL, variant_id VARCHAR(36) NOT NULL, variant_name VARCHAR(255) NOT NULL, variant_identifier VARCHAR(255) NOT NULL, variant_reviewed TINYINT NOT NULL, variant_can_be_current_author TINYINT NOT NULL, main_author_id VARCHAR(36) NOT NULL, main_author_name VARCHAR(255) NOT NULL, main_author_reviewed TINYINT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_run_id (run_id), INDEX IDX_variant_id (variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
    'CREATE TABLE _author_merge_asset_author (id INT UNSIGNED AUTO_INCREMENT NOT NULL, run_id VARCHAR(36) NOT NULL, asset_id VARCHAR(36) NOT NULL, author_id VARCHAR(36) NOT NULL, main_author_id VARCHAR(36) NOT NULL, INDEX IDX_run_id (run_id), INDEX IDX_asset_id (asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
] as $sql) {
    $input = new ArrayInput([
        'command' => 'dbal:run-sql',
        'sql' => $sql,
    ]);
    $input->setInteractive(false);
    $app->run($input, $output);
}

# Console user
$input = new ArrayInput([
    'command' => 'dbal:run-sql',
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

