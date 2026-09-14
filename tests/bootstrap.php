<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$debug = $_SERVER['APP_DEBUG'] ?? false;

if ($debug === true || $debug === '1' || $debug === 1) {
    umask(0000);
}
