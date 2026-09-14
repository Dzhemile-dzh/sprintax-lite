<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$projectDir = dirname(__DIR__);
$envFile = $projectDir.DIRECTORY_SEPARATOR.'.env';

if (!is_file($envFile)) {
    $envFile = $projectDir.DIRECTORY_SEPARATOR.'.env.example';
}

(new Dotenv())->bootEnv($envFile);

$debug = $_SERVER['APP_DEBUG'] ?? false;

if ($debug === true || $debug === '1' || $debug === 1) {
    umask(0000);
}
