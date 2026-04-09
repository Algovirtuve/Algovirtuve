<?php

namespace Deployer;

require 'recipe/laravel.php';

// General settings
set('application', 'algovirtuve');
set('repository', 'git@github.com:Algovirtuve/Algovirtuve.git');
set('default_timeout', 480); // 8 minutes
set('update_code_strategy', 'clone'); // No git bare, as we want .git to be available in the release directory

// Future use?
add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts
host('algovirtuve.linux123123.com')
    ->set('hostname', '45.43.163.102')
    ->set('remote_user', 'algovirtuve')
    ->set('deploy_path', '/home/algovirtuve/algovirtuve')
    ->set('forward_agent', true)
    ->setLabels([
        'type' => 'production',
    ]);

// Check Node.js >= 18
desc('Check if Node.js >= 18 is installed');
task('deploy:ensure_node', function () {
    $output = run('if command -v node >/dev/null 2>&1; then node -v; else echo "not found"; fi');
    if ($output === 'not found') {
        writeln('<error>Node.js is not installed! Please install Node.js version 18 or higher.</error>');
        exit(1);
    }
    $ver = preg_replace('/^v/', '', trim($output));
    $parts = explode('.', $ver);
    if ((int) $parts[0] < 18) {
        writeln('<error>Node.js version 18 or higher is required. Detected: '.$output.'</error>');
        exit(1);
    }
});

// Install Node.js dependencies using npm
desc('Install frontend dependencies with npm');
task('deploy:npm_install', function () {
    run('cd {{release_path}} && npm ci');
});

// Build frontend assets
desc('Build frontend assets');
task('deploy:npm_build', function () {
    run('cd {{release_path}} && npm run build');
});

// Deployment task
desc('Deploys your project');
task('deploy', [
    // Prepare deployment
    'deploy:prepare',

    // Deps
    'deploy:ensure_node',
    'deploy:vendors',
    'deploy:npm_install',
    'deploy:npm_build',

    // Public folder symlink
    'artisan:storage:link',

    // Optimizations
    'artisan:cache:clear',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'artisan:optimize',

    // Migration
    'artisan:migrate',

    // Restart queue
    'artisan:queue:restart',

    // Finalize deployment
    'deploy:publish',
]);

after('deploy:failed', 'deploy:unlock');
